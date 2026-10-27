<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use App\Models\SesMessageStatus;
use App\Services\Ses\ReconcileDelivery;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReconcileDeliveryTest extends TestCase
{
    use RefreshDatabase;
    use CampaignDeliveryTestSupport;

    /** @test */
    public function historical_vdm_delivery_is_applied_without_resending_or_touching_other_recipients(): void
    {
        $this->actingAs($this->createUserWithWorkspace());
        $message = $this->campaignMessage();
        $client = Mockery::mock(SesV2Client::class);
        $client->shouldReceive('getMessageInsights')->once()->with(['MessageId' => $message->message_id])->andReturn(new Result([
            'Insights' => [
                ['Destination' => 'wrong@example.test', 'Events' => [['Type' => 'BOUNCE', 'Timestamp' => now()->getTimestamp()]]],
                ['Destination' => $message->recipient_email, 'Events' => [['Type' => 'DELIVERY', 'Timestamp' => now()->getTimestamp()]]],
            ],
        ]));
        app(ReconcileDelivery::class)->handle($message, $client, 'ap-southeast-1');
        $this->assertNotNull($message->fresh()->delivered_at);
        $this->assertNull($message->fresh()->bounced_at);
        $this->assertNotNull(SesMessageStatus::firstOrFail()->vdm_checked_at);
    }

    /** @test */
    public function vdm_reconciliation_is_disabled_by_default(): void
    {
        $this->artisan('ses:reconcile-delivery')->expectsOutputToContain('disabled')->assertSuccessful();
    }

    /** @test */
    public function enabled_reconciliation_selects_recent_ses_messages_and_records_its_cursor(): void
    {
        $this->actingAs($this->createUserWithWorkspace());
        $message = $this->campaignMessage();
        $this->campaignMessage(['sent_at' => now()->subDays(31)]);
        $this->campaignMessage(['delivered_at' => now()]);
        $client = Mockery::mock(SesV2Client::class);
        $client->shouldReceive('getMessageInsights')->once()->with(['MessageId' => $message->message_id])->andReturn(new Result([
            'Insights' => [['Destination' => $message->recipient_email, 'Events' => [['Type' => 'DELIVERY', 'Timestamp' => now()->getTimestamp()]]]],
        ]));
        $sdk = Mockery::mock();
        $sdk->shouldReceive('createClient')->once()->with('sesv2', Mockery::type('array'))->andReturn($client);
        app()->instance('aws', $sdk);
        config(['sendportal-ses-events.reconcile_enabled' => true]);
        $this->artisan('ses:reconcile-delivery', ['--limit' => 1])->assertSuccessful();
        $this->assertNotNull($message->fresh()->delivered_at);
        $this->assertNotNull(SesMessageStatus::where('sendportal_message_id', $message->id)->firstOrFail()->vdm_checked_at);
    }
}
