<?php

declare(strict_types=1);

namespace Tests\Feature\Ses;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sendportal\Base\Models\Message;
use Tests\TestCase;

class CampaignRecipientsReportTest extends TestCase
{
    use RefreshDatabase;
    use CampaignDeliveryTestSupport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->createUserWithWorkspace());
    }

    /** @test */
    public function recipient_report_shows_new_status_and_retains_existing_columns(): void
    {
        $message = $this->campaignMessage();
        $this->applyEvent($message, 'Bounce');
        $this->get(route('sendportal.campaigns.reports.recipients', $message->source_id))->assertOk()
            ->assertSee('Cari email penerima')->assertSee('Status')->assertSee('Detail')
            ->assertSee('550 mailbox unavailable')->assertSee('Delivered')->assertSee('Opened')
            ->assertSee('Clicked')->assertSee('Bounced')->assertSee('Complained')
            ->assertSee($message->fresh()->bounced_at->format('d M Y H:i:s T'));
    }

    /** @test */
    public function email_search_is_case_insensitive_literal_and_preserved_in_pagination(): void
    {
        $message = $this->campaignMessage(['recipient_email' => 'alpha_100%tag@example.test']);
        Message::factory()->create(['source_id' => $message->source_id, 'recipient_email' => 'alphaX100ZZtag@example.test', 'sent_at' => now()]);
        Message::factory()->count(50)->create(['source_id' => $message->source_id, 'recipient_email' => 'other@example.test', 'sent_at' => now()]);
        $url = route('sendportal.campaigns.reports.recipients', $message->source_id);
        $this->get($url . '?email=' . urlencode('ALPHA_100%'))->assertOk()
            ->assertSee('alpha_100%tag@example.test')->assertDontSee('alphaX100ZZtag@example.test');
        $this->get($url . '?email=example.test')->assertOk()->assertSee('email=example.test', false)->assertSee('page=2', false);
        $this->get($url . '?email=missing')->assertOk()->assertSee('Tidak ada penerima yang cocok');
    }

    /** @test */
    public function recipients_are_scoped_to_campaign_workspace_and_require_authentication(): void
    {
        $mine = $this->campaignMessage();
        $this->actingAs($this->createUserWithWorkspace());
        $other = $this->campaignMessage();
        $this->get(route('sendportal.campaigns.reports.recipients', $mine->source_id))->assertNotFound();
        $this->get(route('sendportal.campaigns.reports.recipients', $other->source_id))->assertOk()->assertDontSee($mine->recipient_email);
        auth()->logout();
        $this->get(route('sendportal.campaigns.reports.recipients', $other->source_id))->assertRedirect(route('login'));
    }
}
