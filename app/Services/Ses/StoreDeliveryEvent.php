<?php

declare(strict_types=1);

namespace App\Services\Ses;

use App\Models\SesDeliveryEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreDeliveryEvent
{
    public const TYPES = ['Send', 'Delivery', 'DeliveryDelay', 'Bounce', 'Complaint', 'Reject', 'RenderingFailure', 'Open', 'Click'];

    /** Store only event metadata; never retain original headers or email content. */
    public function handle(int $workspaceId, string $region, array $event): ?SesDeliveryEvent
    {
        $type = $event['eventType'] ?? $event['notificationType'] ?? null;
        $type = is_string($type) ? str_replace(' ', '', $type) : null;

        if (! in_array($type, self::TYPES, true)) {
            return null;
        }

        $key = match ($type) {
            'RenderingFailure' => 'failure',
            default => lcfirst($type),
        };
        $data = $event[$key] ?? [];
        $recipients = match ($type) {
            'Bounce' => array_column($data['bouncedRecipients'] ?? [], 'emailAddress'),
            'Complaint' => array_column($data['complainedRecipients'] ?? [], 'emailAddress'),
            'Delivery' => $data['recipients'] ?? [],
            'DeliveryDelay' => array_column($data['delayedRecipients'] ?? [], 'emailAddress'),
            default => $event['mail']['destination'] ?? [],
        };
        $messageId = $event['mail']['messageId'] ?? null;
        $timestamp = $data['timestamp'] ?? $event['mail']['timestamp'] ?? null;

        if (! is_string($messageId) || $messageId === '' || strlen($messageId) > 255 || ! is_array($recipients) || ! $recipients || ! is_string($timestamp)) {
            throw ValidationException::withMessages(['event' => 'Missing SES message ID, recipients or timestamp.']);
        }

        foreach ($recipients as $recipient) {
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['event' => 'Invalid SES recipient.']);
            }
        }

        $recipients = array_values(array_unique(array_map(static fn (string $email): string => strtolower(trim($email)), $recipients)));
        sort($recipients);

        try {
            $time = Carbon::parse($timestamp)->utc();
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['event' => 'Invalid SES timestamp.']);
        }
        $detail = match ($type) {
            'Bounce' => ($data['bounceType'] ?? 'Bounce') . ': ' . ($data['bounceSubType'] ?? ''),
            'Complaint' => $data['complaintFeedbackType'] ?? 'Complaint',
            'DeliveryDelay' => $data['delayType'] ?? 'Delivery delayed',
            'Reject' => $data['reason'] ?? 'Rejected by SES',
            'RenderingFailure' => $data['errorMessage'] ?? 'Template rendering failed',
            default => null,
        };
        $payload = [
            'type' => $type,
            'recipients' => $recipients,
            'detail' => $detail === null ? null : Str::limit(strip_tags((string) $detail), 500, ''),
            'permanent' => ($data['bounceType'] ?? null) === 'Permanent',
            'link' => $type === 'Click' ? Str::limit((string) ($data['link'] ?? ''), 2048, '') : null,
            'diagnostics' => [],
        ];

        foreach (array_merge($data['bouncedRecipients'] ?? [], $data['delayedRecipients'] ?? []) as $recipient) {
            $diagnostic = strip_tags((string) ($recipient['diagnosticCode'] ?? ''));
            $payload['diagnostics'][strtolower($recipient['emailAddress'])] = Str::limit($diagnostic, 500, '');
        }

        ksort($payload['diagnostics']);

        return SesDeliveryEvent::firstOrCreate([
            'event_key' => hash('sha256', json_encode([$workspaceId, $region, $messageId, $time->toISOString(), $payload], JSON_THROW_ON_ERROR)),
        ], [
            'workspace_id' => $workspaceId,
            'region' => $region,
            'message_id' => $messageId,
            'payload' => $payload,
            'occurred_at' => $time,
        ]);
    }
}
