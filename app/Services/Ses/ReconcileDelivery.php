<?php

declare(strict_types=1);

namespace App\Services\Ses;

use App\Models\SesMessageStatus;
use Aws\SesV2\SesV2Client;
use Carbon\Carbon;
use Sendportal\Base\Models\Message;

class ReconcileDelivery
{
    public function handle(Message $message, SesV2Client $client, string $region): void
    {
        $result = $client->getMessageInsights(['MessageId' => $message->message_id]);

        foreach ($result['Insights'] ?? [] as $insight) {
            if (strtolower($insight['Destination'] ?? '') !== strtolower($message->recipient_email)) {
                continue;
            }

            foreach ($insight['Events'] ?? [] as $item) {
                $type = match ($item['Type'] ?? '') {
                    'SEND' => 'Send', 'DELIVERY' => 'Delivery', 'BOUNCE' => 'Bounce',
                    'COMPLAINT' => 'Complaint', 'REJECT' => 'Reject',
                    'RENDERING_FAILURE' => 'RenderingFailure', 'DELIVERY_DELAY' => 'DeliveryDelay',
                    default => null,
                };

                if (! $type || ! isset($item['Timestamp'])) {
                    continue;
                }

                $at = $item['Timestamp'] instanceof \DateTimeInterface
                    ? Carbon::instance($item['Timestamp'])->toISOString()
                    : Carbon::createFromTimestamp((float) $item['Timestamp'])->toISOString();
                $data = match ($type) {
                    'Bounce' => [
                        'bounceType' => $item['Details']['Bounce']['BounceType'] ?? 'Undetermined',
                        'bounceSubType' => $item['Details']['Bounce']['BounceSubType'] ?? '',
                        'bouncedRecipients' => [['emailAddress' => $message->recipient_email, 'diagnosticCode' => $item['Details']['Bounce']['DiagnosticCode'] ?? '']],
                    ],
                    'Complaint' => ['complainedRecipients' => [['emailAddress' => $message->recipient_email]], 'complaintFeedbackType' => $item['Details']['Complaint']['ComplaintFeedbackType'] ?? 'Complaint'],
                    'Delivery' => ['recipients' => [$message->recipient_email]],
                    'DeliveryDelay' => ['delayedRecipients' => [['emailAddress' => $message->recipient_email]]],
                    default => [],
                };
                $key = $type === 'RenderingFailure' ? 'failure' : lcfirst($type);
                $event = app(StoreDeliveryEvent::class)->handle($message->workspace_id, $region, [
                    'eventType' => $type,
                    'mail' => ['messageId' => $message->message_id, 'destination' => [$message->recipient_email], 'timestamp' => $at],
                    $key => array_merge($data, ['timestamp' => $at]),
                ]);

                if ($event) {
                    app(ApplyDeliveryEvent::class)->handle($event->id);
                }
            }
        }

        SesMessageStatus::updateOrCreate(['sendportal_message_id' => $message->id], [
            'workspace_id' => $message->workspace_id, 'vdm_checked_at' => now(),
        ]);
    }
}
