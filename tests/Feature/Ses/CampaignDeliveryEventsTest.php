<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use App\Jobs\ProcessSesDeliveryEvent;
use App\Models\SesDeliveryEvent;
use App\Models\SesMessageStatus;
use App\Services\Ses\ApplyDeliveryEvent;
use App\Services\Ses\StoreDeliveryEvent;
use Aws\Sns\Message as SnsMessage;
use Aws\Sns\MessageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignDeliveryEventsTest extends TestCase
{
    use RefreshDatabase;
    use CampaignDeliveryTestSupport;

    private const TOPIC = 'arn:aws:sns:ap-southeast-1:123456789012:campaign-events';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->createUserWithWorkspace());
        config(['sendportal-ses-events.topics' => [self::TOPIC => auth()->user()->currentWorkspaceId()]]);
        Http::preventStrayRequests();
    }

    /** @test */
    public function signed_sns_notification_is_durably_stored_then_updates_the_report_database(): void
    {
        Queue::fake();
        $message = $this->campaignMessage();
        $event = $this->deliveryEvent($message);
        $event['mail']['headers'] = [['name' => 'Subject', 'value' => 'Private subject']];
        $envelope = $this->signedEnvelope($event);
        auth()->logout(); // SNS has no browser session or workspace token.
        $this->postEnvelope($envelope)->assertOk();
        $this->postEnvelope($envelope)->assertOk();
        $this->assertDatabaseCount('ses_delivery_events', 1);
        $stored = SesDeliveryEvent::firstOrFail();
        $this->assertStringNotContainsString('Private subject', json_encode($stored->payload));
        Queue::assertPushed(ProcessSesDeliveryEvent::class);
        app(ApplyDeliveryEvent::class)->handle($stored->id);
        app(ApplyDeliveryEvent::class)->handle($stored->id);
        $this->assertNotNull($message->fresh()->delivered_at);
        $this->assertNotNull($stored->fresh()->processed_at);
        $this->assertDatabaseHas('ses_message_statuses', ['sendportal_message_id' => $message->id, 'status' => 'Delivery']);
    }

    /** @test */
    public function invalid_signature_untrusted_topic_account_and_unconfigured_receiver_are_rejected(): void
    {
        $message = $this->campaignMessage();
        $envelope = $this->signedEnvelope($this->deliveryEvent($message));
        $envelope['Signature'] = base64_encode('forged');
        $this->postEnvelope($envelope)->assertForbidden();
        $envelope = $this->signedEnvelope($this->deliveryEvent($message));
        $envelope['TopicArn'] .= '-untrusted';
        $this->postEnvelope($envelope)->assertForbidden();
        $event = $this->deliveryEvent($message);
        $event['mail']['sendingAccountId'] = '999999999999';
        $this->postEnvelope($this->signedEnvelope($event))->assertForbidden();
        config(['sendportal-ses-events.topics' => []]);
        $this->postEnvelope($envelope)->assertStatus(503);
        $this->assertDatabaseCount('ses_delivery_events', 0);
        $this->assertNull($message->fresh()->delivered_at);
    }

    /** @test */
    public function validated_subscription_is_confirmed_only_on_the_expected_aws_host(): void
    {
        $url = 'https://sns.ap-southeast-1.amazonaws.com/?Action=ConfirmSubscription&Token=test';
        Http::fake([$url => Http::response('Confirmed', 200)]);
        $envelope = $this->signedEnvelope([], ['Type' => 'SubscriptionConfirmation', 'SubscribeURL' => $url, 'Token' => 'test']);
        $this->postEnvelope($envelope)->assertOk();
        Http::assertSent(fn ($request) => $request->url() === $url);
        $envelope = $this->signedEnvelope([], ['Type' => 'SubscriptionConfirmation', 'SubscribeURL' => 'http://127.0.0.1/private', 'Token' => 'test']);
        $this->postEnvelope($envelope)->assertForbidden();
    }

    /** @test */
    public function events_can_arrive_before_message_id_persistence_and_are_recovered_by_the_scheduled_inbox(): void
    {
        Queue::fake();
        $message = $this->campaignMessage();
        $event = app(StoreDeliveryEvent::class)->handle($message->workspace_id, 'ap-southeast-1', $this->deliveryEvent($message));
        $providerId = $message->message_id;
        $message->update(['message_id' => null]);
        app(ApplyDeliveryEvent::class)->handle($event->id);
        $this->assertNull($event->fresh()->processed_at);
        $message->update(['message_id' => $providerId]);
        $this->artisan('ses:process-events')->assertSuccessful();
        Queue::assertPushed(ProcessSesDeliveryEvent::class, fn ($job) => $job->eventId === $event->id && $job->queue === 'sendportal-webhook-process');
        app(ApplyDeliveryEvent::class)->handle($event->id);
        $this->assertNotNull($message->fresh()->delivered_at);
    }

    /** @test */
    public function late_events_do_not_regress_status_and_complaints_preserve_delivery_history(): void
    {
        $message = $this->campaignMessage();
        $this->applyEvent($message, 'Delivery');
        $this->applyEvent($message, 'Send', [], now()->subMinute()->toISOString());
        $this->applyEvent($message, 'DeliveryDelay', [], now()->addMinute()->toISOString());
        $this->assertSame('Delivery', SesMessageStatus::firstOrFail()->status);
        // A stronger outcome wins even when its event arrives out of timestamp order.
        $this->applyEvent($message, 'Complaint', [], now()->subMinutes(2)->toISOString());
        $this->assertSame('Complaint', SesMessageStatus::firstOrFail()->status);
        $this->assertNotNull($message->fresh()->delivered_at);
        $this->assertNotNull($message->fresh()->complained_at);
        $this->assertNotNull($message->subscriber->fresh()->unsubscribed_at);
    }

    /** @test */
    public function hard_bounces_suppress_only_the_matching_recipient_and_transient_bounces_do_not_suppress(): void
    {
        $hard = $this->campaignMessage();
        $soft = $this->campaignMessage();
        $this->applyEvent($hard, 'Bounce');
        $this->applyEvent($soft, 'Bounce', ['bounceType' => 'Transient']);
        $this->assertNotNull($hard->subscriber->fresh()->unsubscribed_at);
        $this->assertNull($soft->subscriber->fresh()->unsubscribed_at);
        $this->assertNotNull($soft->fresh()->bounced_at);
        $this->assertStringContainsString('550 mailbox unavailable', SesMessageStatus::where('sendportal_message_id', $hard->id)->firstOrFail()->detail);
    }

    /** @test */
    public function duplicate_open_events_do_not_increment_counts_twice_or_update_another_workspace(): void
    {
        $message = $this->campaignMessage();
        $this->actingAs($this->createUserWithWorkspace());
        $other = $this->campaignMessage(['message_id' => $message->message_id, 'recipient_email' => $message->recipient_email]);
        $at = now()->toISOString();
        $this->applyEvent($message, 'Open', [], $at);
        $this->applyEvent($message, 'Open', [], $at);
        $this->assertSame(1, $message->fresh()->open_count);
        $this->assertSame(0, $other->fresh()->open_count);
        $this->assertNull($other->fresh()->opened_at);
    }

    /** @test */
    public function wrong_recipient_and_wrong_region_remain_unmatched(): void
    {
        $message = $this->campaignMessage();
        $payload = $this->deliveryEvent($message, 'Delivery', ['recipients' => ['wrong@example.test']]);
        $event = app(StoreDeliveryEvent::class)->handle($message->workspace_id, 'ap-southeast-1', $payload);
        app(ApplyDeliveryEvent::class)->handle($event->id);
        $this->assertNull($event->fresh()->processed_at);
        $event = app(StoreDeliveryEvent::class)->handle($message->workspace_id, 'us-east-1', $this->deliveryEvent($message));
        app(ApplyDeliveryEvent::class)->handle($event->id);
        $this->assertNull($event->fresh()->processed_at);
        $this->assertNull($message->fresh()->delivered_at);
    }

    /** @test */
    public function queue_outage_does_not_discard_the_persisted_event(): void
    {
        $message = $this->campaignMessage();
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->postEnvelope($this->signedEnvelope($this->deliveryEvent($message)))->assertOk();
        $this->assertDatabaseCount('ses_delivery_events', 1);
        $this->assertNull(SesDeliveryEvent::firstOrFail()->processed_at);
    }

    /** @test */
    public function reject_rendering_failure_and_delayed_events_are_recorded_without_false_delivery(): void
    {
        foreach (['Reject', 'RenderingFailure', 'DeliveryDelay'] as $type) {
            $message = $this->campaignMessage();
            $this->applyEvent($message, $type);
            $this->assertDatabaseHas('ses_message_statuses', ['sendportal_message_id' => $message->id, 'status' => $type]);
            $this->assertNull($message->fresh()->delivered_at);
        }
    }

    private function signedEnvelope(array $event, array $overrides = []): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'SNS test'], $key);
        $certificate = openssl_csr_sign($csr, null, $key, 1);
        openssl_x509_export($certificate, $pem);
        // Exercise the real AWS validator, substituting only certificate retrieval.
        $validator = new MessageValidator(static fn (string $url): string => $pem);
        app()->instance(MessageValidator::class, $validator);
        $envelope = array_merge([
            'Type' => 'Notification', 'MessageId' => (string) Str::uuid(), 'TopicArn' => self::TOPIC,
            'Timestamp' => now()->toISOString(), 'Message' => json_encode($event),
            'SignatureVersion' => '2', 'Signature' => '',
            'SigningCertURL' => 'https://sns.ap-southeast-1.amazonaws.com/test.pem',
        ], $overrides);
        openssl_sign($validator->getStringToSign(new SnsMessage($envelope)), $signature, $key, OPENSSL_ALGO_SHA256);
        $envelope['Signature'] = base64_encode($signature);

        return $envelope;
    }

    private function postEnvelope(array $envelope)
    {
        return $this->call('POST', route('sendportal.api.webhooks.aws'), [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode($envelope));
    }
}
