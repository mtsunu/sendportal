<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Models\Sender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\Message;
use Sendportal\Base\Models\Subscriber;
use Sendportal\Base\Pipelines\Campaigns\CreateMessages;
use Tests\TestCase;

class CampaignSenderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     *
     * D-09 and D-12: sender edits and deletion never mutate or hide campaign
     * or message From snapshots across draft, queued, sending, and sent rows.
     */
    public function sender_lifecycle_changes_do_not_mutate_historical_snapshots(): void
    {
        $user = $this->createUserWithWorkspace();
        $this->actingAs($user);
        $workspace = $user->currentWorkspace();
        $originalFromName = 'Original Campaign Sender';
        $originalFromEmail = 'original-sender@example.test';

        $campaign = Campaign::create([
            'workspace_id' => $workspace->id,
            'name' => 'Historical Campaign',
            'subject' => 'Historical subject',
            'from_name' => $originalFromName,
            'from_email' => $originalFromEmail,
        ]);
        $sender = $workspace->senders()->where([
            'from_name' => $originalFromName,
            'from_email' => $originalFromEmail,
        ])->firstOrFail();

        $states = [
            'draft' => ['queued_at' => null, 'sent_at' => null],
            'queued' => ['queued_at' => now()->subMinutes(3), 'sent_at' => null],
            'sending' => ['queued_at' => now()->subMinutes(2), 'sent_at' => null],
            'sent' => ['queued_at' => now()->subMinute(), 'sent_at' => now()],
        ];
        $messages = [];

        foreach ($states as $state => $timestamps) {
            $subscriber = Subscriber::factory()->create([
                'workspace_id' => $workspace->id,
                'email' => $state.'-subscriber@example.test',
            ]);
            $messages[$state] = $this->createMessageSnapshot($campaign, $subscriber, array_merge([
                'subject' => 'Historical subject',
                'from_name' => $originalFromName,
                'from_email' => $originalFromEmail,
            ], $timestamps));
        }

        $sender->update([
            'label' => 'Changed Sender Label',
            'from_name' => 'Changed Sender Name',
            'from_email' => 'changed-sender@example.test',
        ]);
        $sender->delete();

        $this->assertDatabaseMissing('senders', ['id' => $sender->id]);
        $this->assertDatabaseHas('sendportal_campaigns', [
            'id' => $campaign->id,
            'from_name' => $originalFromName,
            'from_email' => $originalFromEmail,
        ]);
        $this->assertSame(4, $campaign->fresh()->messages()->count());

        foreach ($messages as $message) {
            $this->assertDatabaseHas('sendportal_messages', [
                'id' => $message->id,
                'source_id' => $campaign->id,
                'from_name' => $originalFromName,
                'from_email' => $originalFromEmail,
            ]);
            $this->assertNotNull(Message::query()->find($message->id));
        }
    }

    /**
     * @test
     *
     * D-10 and D-11: CreateMessages reads the stored campaign snapshot, and
     * a direct campaign From edit becomes authoritative for later messages.
     */
    public function later_message_generation_uses_the_current_campaign_snapshot(): void
    {
        $user = $this->createUserWithWorkspace();
        $this->actingAs($user);
        $workspace = $user->currentWorkspace();
        $campaign = Campaign::create([
            'workspace_id' => $workspace->id,
            'name' => 'Message Snapshot Campaign',
            'subject' => 'Message snapshot subject',
            'from_name' => 'Stored Sender Name',
            'from_email' => 'stored-sender@example.test',
            'send_to_all' => true,
            'save_as_draft' => true,
        ]);
        $sender = $workspace->senders()->where('from_email', 'stored-sender@example.test')->firstOrFail();
        $firstSubscriber = Subscriber::factory()->create(['workspace_id' => $workspace->id]);

        $this->runMessageCreation($campaign);
        $firstMessage = Message::query()
            ->where('source_id', $campaign->id)
            ->where('subscriber_id', $firstSubscriber->id)
            ->firstOrFail();

        $sender->delete();
        $campaign->update([
            'from_name' => 'Edited Campaign Name',
            'from_email' => 'edited-campaign@example.test',
        ]);
        $secondSubscriber = Subscriber::factory()->create(['workspace_id' => $workspace->id]);

        $this->runMessageCreation($campaign->fresh());
        $secondMessage = Message::query()
            ->where('source_id', $campaign->id)
            ->where('subscriber_id', $secondSubscriber->id)
            ->firstOrFail();

        $this->assertSame('Stored Sender Name', $firstMessage->from_name);
        $this->assertSame('stored-sender@example.test', $firstMessage->from_email);
        $this->assertSame('Edited Campaign Name', $secondMessage->from_name);
        $this->assertSame('edited-campaign@example.test', $secondMessage->from_email);
        $this->assertDatabaseMissing('senders', ['id' => $sender->id]);
        $this->assertSame(2, $campaign->fresh()->messages()->count());
    }

    /**
     * @test
     *
     * A2, A3, A4, and D-09: equal campaign values remain separate rows,
     * normalized sender equality deduplicates only the sender pair, and
     * adjacent values remain independently addressable without list ordering.
     */
    public function independent_rows_do_not_merge_or_depend_on_collection_order(): void
    {
        $user = $this->createUserWithWorkspace();
        $this->actingAs($user);
        $workspaceId = $user->currentWorkspace()->id;
        $identicalAttributes = [
            'workspace_id' => $workspaceId,
            'name' => 'Equal Campaign',
            'subject' => 'Equal subject',
            'from_name' => 'Equal Sender',
            'from_email' => 'equal@example.test',
        ];
        $identicalCampaigns = [
            Campaign::create($identicalAttributes),
            Campaign::create($identicalAttributes),
        ];
        $adjacentCampaigns = [
            Campaign::create(array_merge($identicalAttributes, [
                'name' => 'Adjacent Campaign A',
                'from_email' => 'adjacent-a@example.test',
            ])),
            Campaign::create(array_merge($identicalAttributes, [
                'name' => 'Adjacent Campaign B',
                'from_email' => 'adjacent-b@example.test',
            ])),
        ];

        $campaignIds = collect(array_merge($identicalCampaigns, $adjacentCampaigns))
            ->map(static fn (Campaign $campaign): int => (int) $campaign->id)
            ->all();
        $this->assertCount(4, array_unique($campaignIds));
        $this->assertSame(3, Sender::query()->where('workspace_id', $workspaceId)->count());

        foreach ($campaignIds as $campaignId) {
            $subscriber = Subscriber::factory()->create(['workspace_id' => $workspaceId]);
            $message = $this->createMessageSnapshot(
                Campaign::query()->findOrFail($campaignId),
                $subscriber,
                ['from_name' => 'Snapshot '.$campaignId, 'from_email' => 'snapshot-'.$campaignId.'@example.test']
            );

            $this->assertSame($campaignId, (int) $message->source_id);
            $this->assertSame(1, Message::query()->where('source_id', $campaignId)->count());
        }

        $this->assertFalse(method_exists(Campaign::class, 'sender'));
        $this->assertFalse(Schema::hasColumn('sendportal_campaigns', 'sender_id'));
        $this->assertFalse(Schema::hasColumn('sendportal_messages', 'sender_id'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createMessageSnapshot(Campaign $campaign, Subscriber $subscriber, array $overrides = []): Message
    {
        return Message::create(array_merge([
            'workspace_id' => $campaign->workspace_id,
            'subscriber_id' => $subscriber->id,
            'source_type' => Campaign::class,
            'source_id' => $campaign->id,
            'recipient_email' => $subscriber->email,
            'subject' => $campaign->subject,
            'from_name' => $campaign->from_name,
            'from_email' => $campaign->from_email,
            'queued_at' => null,
            'sent_at' => null,
        ], $overrides));
    }

    private function runMessageCreation(Campaign $campaign): void
    {
        (new CreateMessages())->handle($campaign, static fn (Campaign $campaign): Campaign => $campaign);
    }
}
