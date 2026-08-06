<?php

declare(strict_types=1);

namespace Tests\Feature\Workspaces;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
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
        $this->assertStringNotContainsString('<option value="">Choose a saved sender</option> selected', $html);
        $this->assertStringNotContainsString('sender_id', $html);
        $this->assertStringContainsString('Acme News — news@example.test', $html);
        $this->assertStringContainsString('Zebra Updates — zebra@example.test', $html);
        $this->assertStringNotContainsString('Private Sender', $html);
        $this->assertStringContainsString('name="from_name"', $html);
        $this->assertStringContainsString('name="from_email"', $html);
        $this->assertStringContainsString("$('input[name=\"from_name\"]').val(option.dataset.fromName || '');", $html);
        $this->assertStringContainsString("$('input[name=\"from_email\"]').val(option.dataset.fromEmail || '');", $html);
    }

    /**
     * @test
     *
     * SENDER-06, D-03, and D-05: edit values remain authoritative until an
     * explicit picker change, and the picker never infers a sender.
     */
    public function edit_preserves_existing_from_values_without_selecting_a_matching_sender(): void
    {
        $user = $this->createUserWithWorkspace();
        $user->currentWorkspace()->senders()->create([
            'label' => 'Existing Saved Sender',
            'from_name' => 'Existing Name',
            'from_email' => 'existing@example.test',
        ]);

        $this->actingAs($user);
        $html = $this->renderCampaignView('sendportal::campaigns.edit', [
            'campaign' => (object) [
                'id' => 7,
                'name' => 'Existing Campaign',
                'subject' => 'Existing Subject',
                'from_name' => 'Existing Name',
                'from_email' => 'existing@example.test',
                'template_id' => null,
                'email_service_id' => 1,
                'is_open_tracking' => true,
                'is_click_tracking' => true,
                'content' => 'Existing content',
            ],
        ], 'sendportal.campaigns.update');

        $this->assertStringContainsString('value="Existing Name"', $html);
        $this->assertStringContainsString('value="existing@example.test"', $html);
        $this->assertStringContainsString('<option value="">Choose a saved sender</option>', $html);
        $this->assertStringNotContainsString('selected', $this->senderPickerMarkup($html));
        $this->assertSame(1, substr_count($html, 'id="campaign-sender-picker"'));
    }

    /**
     * @test
     *
     * SENDER-06 and D-04: an empty library leaves the enabled manual form
     * usable and does not add an empty-state block or submitted identity.
     */
    public function empty_sender_collection_renders_an_enabled_blank_picker_and_manual_fields(): void
    {
        $user = $this->createUserWithWorkspace();

        $this->actingAs($user);
        $html = $this->renderCampaignView('sendportal::campaigns.create', [
            'senders' => new Collection(),
        ]);

        $picker = $this->senderPickerMarkup($html);

        $this->assertStringContainsString('<select id="campaign-sender-picker" class="form-control">', $picker);
        $this->assertSame(1, substr_count($picker, '<option'));
        $this->assertStringContainsString('input[name="from_name"]', $html);
        $this->assertStringContainsString('input[name="from_email"]', $html);
        $this->assertStringNotContainsString('disabled', $picker);
        $this->assertStringNotContainsString('name=', $picker);
        $this->assertStringNotContainsString('sender_id', $html);
    }

    /**
     * @test
     *
     * D-04: the shared package partial remains render-safe when a caller has
     * no sender collection at all, while keeping the manual campaign fields.
     */
    public function shared_partial_falls_back_to_an_enabled_blank_picker_without_sender_data(): void
    {
        $html = $this->renderCampaignPartial();

        $picker = $this->senderPickerMarkup($html);

        $this->assertSame(1, substr_count($picker, '<option'));
        $this->assertStringNotContainsString('disabled', $picker);
        $this->assertStringContainsString('name="from_name"', $html);
        $this->assertStringContainsString('name="from_email"', $html);
    }

    /**
     * @test
     *
     * T-06-07 and D-02: user-controlled sender values are escaped in both
     * option text and data attributes, including unusually long values.
     */
    public function hostile_and_long_sender_values_are_escaped_and_rendered_safely(): void
    {
        $user = $this->createUserWithWorkspace();
        $label = 'A "quoted" <script>alert(1)</script> sender ' . str_repeat('L', 300);
        $fromName = 'Name "quoted" <b>unsafe</b> ' . str_repeat('N', 300);
        $fromEmail = 'hostile+tag@example.test';
        $user->currentWorkspace()->senders()->create([
            'label' => $label,
            'from_name' => $fromName,
            'from_email' => $fromEmail,
        ]);

        $this->actingAs($user);
        $html = $this->renderCampaignView('sendportal::campaigns.create');

        $this->assertStringContainsString('&quot;quoted&quot;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('data-from-name="Name &quot;quoted&quot; &lt;b&gt;unsafe&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString(str_repeat('L', 300), $html);
    }

    /**
     * @test
     *
     * T-06-08, T-06-10, and D-03 through D-05: only a change event writes
     * the existing package fields, including clearing both on blank reset.
     */
    public function picker_script_is_change_only_and_covers_both_fields_and_blank_reset(): void
    {
        $user = $this->createUserWithWorkspace();

        $this->actingAs($user);
        $html = $this->renderCampaignView('sendportal::campaigns.create');
        $script = $this->scriptMarkup($html);

        $this->assertStringContainsString("$('#campaign-sender-picker').on('change', function ()", $script);
        $this->assertStringContainsString("option.dataset.fromName || ''", $script);
        $this->assertStringContainsString("option.dataset.fromEmail || ''", $script);
        $this->assertStringContainsString("$('input[name=\"from_name\"]').val(option.dataset.fromName || '')", $script);
        $this->assertStringContainsString("$('input[name=\"from_email\"]').val(option.dataset.fromEmail || '')", $script);
        $this->assertStringNotContainsString("$('#campaign-sender-picker').val(", $script);
        $this->assertStringNotContainsString('sender_id', $html);
    }

    /**
     * Render a package campaign wrapper with the same lightweight fixture as
     * the tracer, while allowing edit-specific data and an explicit sender
     * collection for edge-case coverage.
     *
     * @param array<string, mixed> $overrides
     */
    private function renderCampaignView(string $view, array $overrides = [], string $routeName = 'sendportal.campaigns.create'): string
    {
        request()->setRouteResolver(
            static fn (): Route => (new Route(['GET'], '/campaigns', static fn () => null))
                ->name($routeName)
        );

        return view($view, array_merge([
            'templates' => [null => '- None -'],
            'emailServices' => collect([
                (object) [
                    'id' => 1,
                    'formatted_name' => 'SMTP (SMTP)',
                    'type_id' => 1,
                ],
            ]),
        ], $overrides))->render();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function renderCampaignPartial(array $overrides = []): string
    {
        request()->setRouteResolver(
            static fn (): Route => (new Route(['GET'], '/campaigns', static fn () => null))
                ->name('sendportal.campaigns.create')
        );

        return view('sendportal::campaigns.partials.form', array_merge([
            'templates' => [null => '- None -'],
            'emailServices' => collect([
                (object) [
                    'id' => 1,
                    'formatted_name' => 'SMTP (SMTP)',
                    'type_id' => 1,
                ],
            ]),
        ], $overrides))->render();
    }

    private function senderPickerMarkup(string $html): string
    {
        $start = strpos($html, '<select id="campaign-sender-picker"');
        $end = strpos($html, '</select>', $start);

        return substr($html, $start, $end - $start + strlen('</select>'));
    }

    private function scriptMarkup(string $html): string
    {
        $pickerPosition = strpos($html, "$('#campaign-sender-picker')");
        $start = strrpos(substr($html, 0, $pickerPosition), '<script>');
        $end = strpos($html, '</script>', $start);

        return substr($html, $start, $end - $start + strlen('</script>'));
    }
}
