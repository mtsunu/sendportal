<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use App\Services\Ses\ApplyDeliveryEvent;
use App\Services\Ses\StoreDeliveryEvent;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\EmailService;
use Sendportal\Base\Models\EmailServiceType;
use Sendportal\Base\Models\Message;
use Sendportal\Base\Models\Subscriber;

trait CampaignDeliveryTestSupport
{
    protected function campaignMessage(array $overrides = []): Message
    {
        $workspaceId = auth()->user()->currentWorkspaceId();
        $provider = EmailService::factory()->create([
            'workspace_id' => $workspaceId, 'type_id' => EmailServiceType::SES,
            'settings' => ['region' => 'ap-southeast-1', 'key' => 'test-key', 'secret' => 'test-secret'],
        ]);
        $campaign = Campaign::factory()->sent()->create(['workspace_id' => $workspaceId, 'email_service_id' => $provider->id]);
        $subscriber = Subscriber::factory()->create(['workspace_id' => $workspaceId]);

        return Message::factory()->create(array_merge([
            'workspace_id' => $workspaceId, 'source_type' => Campaign::class, 'source_id' => $campaign->id,
            'subscriber_id' => $subscriber->id, 'recipient_email' => $subscriber->email,
            'message_id' => 'ses-' . $subscriber->id, 'sent_at' => now(),
        ], $overrides));
    }

    protected function deliveryEvent(Message $message, string $type = 'Delivery', array $data = [], ?string $at = null): array
    {
        $at ??= now()->toISOString();
        $key = $type === 'RenderingFailure' ? 'failure' : lcfirst($type);
        $eventData = match ($type) {
            'Bounce' => ['bounceType' => 'Permanent', 'bounceSubType' => 'General', 'bouncedRecipients' => [['emailAddress' => $message->recipient_email, 'diagnosticCode' => 'smtp; 550 mailbox unavailable']]],
            'Complaint' => ['complainedRecipients' => [['emailAddress' => $message->recipient_email]], 'complaintFeedbackType' => 'abuse'],
            'Delivery' => ['recipients' => [$message->recipient_email]],
            'DeliveryDelay' => ['delayType' => 'MailboxFull', 'delayedRecipients' => [['emailAddress' => $message->recipient_email]]],
            default => [],
        };

        return [
            'eventType' => $type,
            'mail' => ['sendingAccountId' => '123456789012', 'messageId' => $message->message_id, 'destination' => [$message->recipient_email], 'timestamp' => $at],
            $key => array_merge($eventData, ['timestamp' => $at], $data),
        ];
    }

    protected function applyEvent(Message $message, string $type, array $data = [], ?string $at = null): void
    {
        $event = app(StoreDeliveryEvent::class)->handle($message->workspace_id, 'ap-southeast-1', $this->deliveryEvent($message, $type, $data, $at));
        app(ApplyDeliveryEvent::class)->handle($event->id);
    }
}
