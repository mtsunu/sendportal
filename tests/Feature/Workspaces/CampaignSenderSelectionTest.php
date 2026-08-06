<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

class CampaignSenderSelectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     *
     * SENDER-06, SENDER-08, D-01, D-02, D-03, and D-05: the create-form
     * tracer is rendered through the host/package view seam.
     */
    public function an_authenticated_user_sees_only_active_workspace_senders_in_the_create_picker(): void
    {
        $user = $this->createUserWithWorkspace();
        $foreignUser = $this->createUserWithWorkspace();

        $user->currentWorkspace()->senders()->create([
            'label' => 'Zebra Updates',
            'from_name' => 'Zebra Team',
            'from_email' => 'zebra@example.test',
        ]);
        $user->currentWorkspace()->senders()->create([
            'label' => 'Acme News',
            'from_name' => 'Acme Team',
            'from_email' => 'news@example.test',
        ]);
        $foreignUser->currentWorkspace()->senders()->create([
            'label' => 'Private Sender',
            'from_name' => 'Private Team',
            'from_email' => 'private@example.test',
        ]);

        $this->actingAs($user);
        request()->setRouteResolver(
            static fn (): Route => (new Route(['GET'], '/campaigns/create', static fn () => null))
                ->name('sendportal.campaigns.create')
        );

        $html = view('sendportal::campaigns.create', [
            'templates' => [null => '- None -'],
            'emailServices' => collect([
                (object) [
                    'id' => 1,
                    'formatted_name' => 'SMTP (SMTP)',
                    'type_id' => 1,
                ],
            ]),
        ])->render();

        $this->assertSame(1, substr_count($html, 'id="campaign-sender-picker"'));
        $this->assertStringContainsString('<label for="campaign-sender-picker"', $html);
        $this->assertStringContainsString('Saved Sender', $html);
        $this->assertStringContainsString('<option value="">Choose a saved sender</option>', $html);
        $this->assertStringNotContainsString('name="campaign-sender-picker"', $html);
        $this->assertStringNotContainsString('selected', $html);
        $this->assertStringContainsString('Acme News — news@example.test', $html);
        $this->assertStringContainsString('Zebra Updates — zebra@example.test', $html);
        $this->assertStringNotContainsString('Private Sender', $html);
        $this->assertStringContainsString('input[name="from_name"]', $html);
        $this->assertStringContainsString('input[name="from_email"]', $html);
        $this->assertStringContainsString("$('input[name=\"from_name\"]').val(option.dataset.fromName || '');", $html);
        $this->assertStringContainsString("$('input[name=\"from_email\"]').val(option.dataset.fromEmail || '');", $html);
    }
}
