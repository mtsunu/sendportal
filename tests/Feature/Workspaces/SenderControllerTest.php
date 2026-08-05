<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SenderControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     *
     * @group sender_test
     */
    public function an_active_workspace_owner_can_create_and_list_a_sender(): void
    {
        $user = $this->createUserWithWorkspace();

        $this->actingAs($user);

        $this->get(route('senders.index'))
            ->assertOk()
            ->assertSee('Senders')
            ->assertSee('Add Sender');

        $response = $this->post(route('senders.store'), [
            'label' => '  Product Updates  ',
            'from_name' => 'Acme Mail',
            'from_email' => '  NEWS@ACME.TEST  ',
        ]);

        $response->assertRedirect(route('senders.index'));
        $response->assertSessionHas('success', 'Sender saved successfully.');

        $this->get(route('senders.index'))
            ->assertOk()
            ->assertSee('Product Updates')
            ->assertSee('Acme Mail')
            ->assertSee('news@acme.test');

        $this->assertDatabaseHas('senders', [
            'workspace_id' => $user->currentWorkspace()->id,
            'label' => 'Product Updates',
            'from_name' => 'Acme Mail',
            'from_email' => 'news@acme.test',
        ]);
    }

    /**
     * @test
     *
     * @group sender_test
     */
    public function an_active_workspace_member_can_view_and_create_shared_senders_without_cross_workspace_rows(): void
    {
        $workspace = Workspace::factory()->create();
        $member = $this->createWorkspaceUser($workspace);
        $otherWorkspaceOwner = $this->createUserWithWorkspace();

        $this->actingAs($otherWorkspaceOwner);
        $this->post(route('senders.store'), [
            'label' => 'Private Sender',
            'from_name' => 'Other Workspace',
            'from_email' => 'other@example.test',
        ])->assertRedirect(route('senders.index'));

        $this->actingAs($member);

        $this->get(route('senders.index'))
            ->assertOk()
            ->assertSee('Senders')
            ->assertDontSee('Private Sender');

        $this->post(route('senders.store'), [
            'label' => 'Shared Sender',
            'from_name' => 'Workspace Team',
            'from_email' => 'team@example.test',
        ])->assertRedirect(route('senders.index'));

        $this->assertDatabaseHas('senders', [
            'workspace_id' => $workspace->id,
            'label' => 'Shared Sender',
        ]);
    }

    /**
     * @test
     *
     * @group sender_test
     */
    public function an_active_workspace_member_can_edit_a_shared_sender_and_sees_the_updated_values(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = $this->createWorkspaceUser($workspace);
        $member = $this->createWorkspaceUser($workspace);
        $sender = $workspace->senders()->create([
            'label' => 'Original Sender',
            'from_name' => 'Original Name',
            'from_email' => 'original@example.test',
        ]);

        $this->actingAs($member);

        $this->get("/senders/{$sender->id}/edit")
            ->assertOk()
            ->assertSee('Edit Sender')
            ->assertSee('Save Changes')
            ->assertSee('value="Original Sender"', false);

        $this->put("/senders/{$sender->id}", [
            'label' => '  Updated Sender  ',
            'from_name' => 'Updated Name',
            'from_email' => '  UPDATED@EXAMPLE.TEST  ',
        ])->assertRedirect(route('senders.index'))
            ->assertSessionHas('success', 'Sender updated successfully.');

        $this->assertDatabaseHas('senders', [
            'id' => $sender->id,
            'workspace_id' => $workspace->id,
            'label' => 'Updated Sender',
            'from_name' => 'Updated Name',
            'from_email' => 'updated@example.test',
        ]);
        $this->assertDatabaseHas('users', ['id' => $creator->id]);

        $this->get(route('senders.index'))
            ->assertOk()
            ->assertSee('Updated Sender')
            ->assertSee('Updated Name')
            ->assertSee('updated@example.test');
    }

    /**
     * @test
     *
     * @group sender_test
     */
    public function an_active_workspace_member_can_delete_a_shared_sender_and_the_empty_state_is_rendered(): void
    {
        $workspace = Workspace::factory()->create();
        $member = $this->createWorkspaceUser($workspace);
        $sender = $workspace->senders()->create([
            'label' => 'Delete Me',
            'from_name' => 'Delete Name',
            'from_email' => 'delete@example.test',
        ]);

        $this->actingAs($member);

        $this->get(route('senders.index'))
            ->assertOk()
            ->assertSee('Edit Sender')
            ->assertSee('Delete Sender')
            ->assertSee("onsubmit=\"return confirm('Are you sure you want to delete this sender? This action cannot be undone.')\"", false);

        $this->delete("/senders/{$sender->id}")
            ->assertRedirect(route('senders.index'))
            ->assertSessionHas('success', 'Sender deleted successfully.');

        $this->assertDatabaseMissing('senders', ['id' => $sender->id]);
        $this->get(route('senders.index'))
            ->assertOk()
            ->assertSee('No saved senders yet')
            ->assertSee('Add a sender to reuse its From Name and From Email when creating campaigns.');
    }

    /**
     * @test
     *
     * @group sender_test
     */
    public function invalid_sender_updates_preserve_values_and_show_field_specific_errors(): void
    {
        $user = $this->createUserWithWorkspace();
        $sender = $user->currentWorkspace()->senders()->create([
            'label' => 'Existing Sender',
            'from_name' => 'Existing Name',
            'from_email' => 'existing@example.test',
        ]);

        $this->actingAs($user);

        $this->from("/senders/{$sender->id}/edit");
        $response = $this->put("/senders/{$sender->id}", [
            'label' => '',
            'from_name' => '',
            'from_email' => 'not-an-email',
        ]);

        $response->assertRedirect("/senders/{$sender->id}/edit")
            ->assertSessionHasErrors(['label', 'from_name', 'from_email']);
        $this->get("/senders/{$sender->id}/edit")
            ->assertOk()
            ->assertSee("We couldn't save this sender. Check the highlighted fields and try again.")
            ->assertSee('The label field is required.')
            ->assertSee('The From Name field is required.')
            ->assertSee('Enter a valid email address.');
    }

    /**
     * @test
     *
     * @group sender_test
     */
    public function normalized_duplicate_pairs_are_rejected_on_create_and_update(): void
    {
        $user = $this->createUserWithWorkspace();
        $workspace = $user->currentWorkspace();
        $existing = $workspace->senders()->create([
            'label' => 'Existing Sender',
            'from_name' => 'Acme',
            'from_email' => 'acme@example.test',
        ]);
        $candidate = $workspace->senders()->create([
            'label' => 'Candidate Sender',
            'from_name' => 'Other',
            'from_email' => 'other@example.test',
        ]);

        $this->actingAs($user);

        $this->post(route('senders.store'), [
            'label' => 'Duplicate',
            'from_name' => ' Acme ',
            'from_email' => ' ACME@EXAMPLE.TEST ',
        ])->assertSessionHasErrors('from_email');

        $this->put("/senders/{$candidate->id}", [
            'label' => 'Updated Duplicate',
            'from_name' => 'Acme',
            'from_email' => 'acme@example.test',
        ])->assertSessionHasErrors('from_email');

        $this->assertDatabaseHas('senders', [
            'id' => $candidate->id,
            'from_name' => 'Other',
            'from_email' => 'other@example.test',
        ]);
        $this->assertDatabaseHas('senders', ['id' => $existing->id]);
    }

    /**
     * @test
     *
     * @group sender_test
     */
    public function foreign_sender_ids_are_concealed_on_edit_update_and_delete_without_mutation(): void
    {
        $user = $this->createUserWithWorkspace();
        $foreignOwner = $this->createUserWithWorkspace();
        $foreignSender = $foreignOwner->currentWorkspace()->senders()->create([
            'label' => 'Foreign Sender',
            'from_name' => 'Foreign Name',
            'from_email' => 'foreign@example.test',
        ]);

        $this->actingAs($user);

        $this->get("/senders/{$foreignSender->id}/edit")->assertNotFound();
        $this->put("/senders/{$foreignSender->id}", [
            'label' => 'Tampered',
            'from_name' => 'Tampered',
            'from_email' => 'tampered@example.test',
        ])->assertNotFound();
        $this->delete("/senders/{$foreignSender->id}")->assertNotFound();

        $this->assertDatabaseHas('senders', [
            'id' => $foreignSender->id,
            'workspace_id' => $foreignOwner->currentWorkspace()->id,
            'label' => 'Foreign Sender',
        ]);
    }

    /**
     * @test
     *
     * @group sender_test
     */
    public function phase_five_exposes_only_the_sender_crud_routes_and_no_later_campaign_sender_seams(): void
    {
        $routeNames = collect(app('router')->getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'senders.'))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'senders.create',
            'senders.destroy',
            'senders.edit',
            'senders.index',
            'senders.store',
            'senders.update',
        ], $routeNames);
        $this->assertNull(app('router')->getRoutes()->getByName('campaigns.sender-selection'));
        $this->assertNull(app('router')->getRoutes()->getByName('campaigns.sender-auto-capture'));
        $this->assertFalse(class_exists('App\\Services\\Campaigns\\SelectCampaignSender'));
        $this->assertFalse(class_exists('App\\Services\\Campaigns\\AutoCaptureCampaignSender'));
    }
}
