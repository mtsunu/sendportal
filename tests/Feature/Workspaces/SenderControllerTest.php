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
}
