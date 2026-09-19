<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\TransactionalEmail;
use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TransactionalEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /** @test */
    public function a_valid_workspace_token_queues_a_transactional_email(): void
    {
        $user = $this->createUserWithWorkspace();
        $token = ApiToken::factory()->create([
            'workspace_id' => $user->currentWorkspace()->id,
        ]);
        $payload = [
            'to' => 'recipient@example.test',
            'subject' => 'Payment received',
            'body' => 'Your payment has been received.',
            'html' => '<p>Your payment has been received.</p>',
        ];

        $response = $this->withToken($token->api_token)
            ->postJson(route('sendportal.api.notifications.email'), $payload);

        $response->assertAccepted()->assertJson([
            'status' => 'queued',
            'message' => 'Transactional email queued.',
        ]);

        Mail::assertQueued(TransactionalEmail::class, function (TransactionalEmail $mail) use ($payload): bool {
            return $mail->hasTo($payload['to'])
                && $mail->emailSubject === $payload['subject']
                && $mail->body === $payload['body']
                && $mail->htmlBody === $payload['html'];
        });
    }

    /** @test */
    public function a_request_without_a_valid_workspace_token_is_rejected(): void
    {
        $response = $this->postJson(route('sendportal.api.notifications.email'), [
            'to' => 'recipient@example.test',
            'subject' => 'Payment received',
            'body' => 'Your payment has been received.',
        ]);

        $response->assertUnauthorized();
        Mail::assertNothingQueued();
    }

    /** @test */
    public function invalid_transactional_email_data_is_rejected_before_queueing(): void
    {
        $user = $this->createUserWithWorkspace();
        $token = ApiToken::factory()->create([
            'workspace_id' => $user->currentWorkspace()->id,
        ]);

        $response = $this->withToken($token->api_token)
            ->postJson(route('sendportal.api.notifications.email'), [
                'to' => 'not-an-email',
                'subject' => '',
                'body' => '',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'to',
            'subject',
            'body',
        ]);
        Mail::assertNothingQueued();
    }
}
