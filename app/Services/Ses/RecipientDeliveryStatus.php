<?php

declare(strict_types=1);

namespace App\Services\Ses;

use Sendportal\Base\Models\Message;

class RecipientDeliveryStatus
{
    public static function for(Message $message): array
    {
        $status = match (true) {
            (bool) $message->complained_at => 'Complaint',
            (bool) $message->bounced_at => 'Bounce',
            in_array($message->ses_status, ['Reject', 'RenderingFailure'], true) => $message->ses_status,
            (bool) $message->delivered_at => 'Delivery',
            default => $message->ses_status ?? 'Unknown',
        };

        return match ($status) {
            'Complaint' => ['Complaint', 'danger'],
            'Bounce' => ['Bounced', 'danger'],
            'Reject' => ['Rejected', 'danger'],
            'RenderingFailure' => ['Failed', 'danger'],
            'Delivery' => ['Delivered', 'success'],
            'DeliveryDelay' => ['Delayed', 'warning'],
            'Send' => ['Accepted by SES', 'info'],
            default => ['Awaiting delivery event', 'secondary'],
        };
    }
}
