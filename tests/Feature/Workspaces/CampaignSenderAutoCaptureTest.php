<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sendportal\Base\Models\Campaign;
use Tests\TestCase;

class CampaignSenderAutoCaptureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     *
     * SENDER-07, D-01, D-02, and D-08: a newly created campaign captures a
     * normalized sender without changing the campaign's submitted values.
     */
    public function a_direct_campaign_creation_captures_its_sender_identity(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspaceId = $user->currentWorkspace()->id;

        $campaign = Campaign::create([
            'workspace_id' => $workspaceId,
            'name' => '  Product Launch  ',
            'from_name' => '  Acme Marketing  ',
            'from_email' => '  NEWS@ACME.TEST  ',
        ]);

        $this->assertDatabaseCount('senders', 1);
        $this->assertDatabaseHas('senders', [
            'workspace_id' => $workspaceId,
            'label' => 'Product Launch',
            'from_name' => 'Acme Marketing',
            'from_email' => 'news@acme.test',
        ]);

        $this->assertSame('  Acme Marketing  ', $campaign->fresh()->from_name);
        $this->assertSame('  NEWS@ACME.TEST  ', $campaign->fresh()->from_email);
    }
}
