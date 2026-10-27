<?php

declare(strict_types=1);

namespace App\Services\Ses;

use App\Models\SesDeliveryEvent;
use App\Models\SesMessageStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\EmailServiceType;
use Sendportal\Base\Models\Message;
use Sendportal\Base\Models\MessageUrl;
use Sendportal\Base\Models\Subscriber;
use Sendportal\Base\Models\UnsubscribeEventType;

class ApplyDeliveryEvent
{
    private const RANKS = ['Send' => 10, 'DeliveryDelay' => 20, 'Delivery' => 30, 'Reject' => 40, 'RenderingFailure' => 40, 'Bounce' => 50, 'Complaint' => 60];

    public function handle(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $event = SesDeliveryEvent::query()->lockForUpdate()->find($id);

            if (! $event || $event->processed_at) {
                return;
            }

            $messages = Message::query()->where('workspace_id', $event->workspace_id)
                ->where('message_id', $event->message_id)->where('source_type', Campaign::class)
                ->whereIn(DB::raw('LOWER(recipient_email)'), $event->payload['recipients'])
                ->orderBy('id')->lockForUpdate()->get();

            if ($messages->isEmpty()) {
                return; // An SNS notification can beat markSent(); the inbox will be replayed.
            }

            foreach ($messages as $message) {
                $campaign = Campaign::query()->where('workspace_id', $event->workspace_id)->find($message->source_id);
                $provider = $campaign?->email_service;

                if (! $provider || $provider->type_id !== EmailServiceType::SES || ($provider->settings['region'] ?? '') !== $event->region) {
                    return;
                }
            }

            foreach ($messages as $message) {
                $this->apply($message, $event);
            }

            $event->update(['processed_at' => now()]);
        }, 3);
    }

    private function apply(Message $message, SesDeliveryEvent $event): void
    {
        $type = $event->payload['type'];
        $at = $event->occurred_at;
        $historicalRank = max($message->complained_at ? 60 : 0, $message->bounced_at ? 50 : 0, $message->delivered_at ? 30 : 0);
        $column = match ($type) {
            'Delivery' => 'delivered_at',
            'Bounce' => 'bounced_at',
            'Complaint' => 'complained_at',
            default => null,
        };

        if ($column && (! $message->{$column} || $at->lt($message->{$column}))) {
            $message->{$column} = $at;
            $message->save();
        }

        if ($type === 'Complaint' || ($type === 'Bounce' && $event->payload['permanent'])) {
            $message->unsubscribed_at ??= $at;
            $message->save();
            Subscriber::query()->where('workspace_id', $message->workspace_id)->whereKey($message->subscriber_id)
                ->whereNull('unsubscribed_at')->update([
                    'unsubscribed_at' => $at,
                    'unsubscribe_event_id' => $type === 'Complaint' ? UnsubscribeEventType::COMPLAINT : UnsubscribeEventType::BOUNCE,
                ]);
        }

        if ($type === 'Open' || $type === 'Click') {
            $link = $event->payload['link'] ?? '';

            if ($type === 'Click' && Str::contains($link, '/subscriptions/unsubscribe')) {
                return;
            }

            if (! $message->opened_at || $at->lt($message->opened_at)) {
                $message->opened_at = $at;
            }

            if ($type === 'Open') {
                ++$message->open_count;
            } else {
                if (! $message->clicked_at || $at->lt($message->clicked_at)) {
                    $message->clicked_at = $at;
                }

                ++$message->click_count;

                if ($link !== '') {
                    $url = MessageUrl::firstOrCreate([
                        'hash' => md5($message->source_type . '_' . $message->source_id . '_' . $link),
                    ], ['source_type' => $message->source_type, 'source_id' => $message->source_id, 'url' => $link, 'click_count' => 0]);
                    $url->increment('click_count');
                }
            }

            $message->save();
        }

        if (! isset(self::RANKS[$type])) {
            return;
        }

        $state = SesMessageStatus::firstOrCreate([
            'sendportal_message_id' => $message->id,
        ], ['workspace_id' => $message->workspace_id]);
        $currentRank = self::RANKS[$state->status] ?? 0;
        // Existing package timestamps are authoritative even before this integration was installed.
        $currentRank = max($currentRank, $historicalRank);

        if (self::RANKS[$type] > $currentRank || (self::RANKS[$type] === $currentRank && (! $state->event_at || $at->gte($state->event_at)))) {
            $diagnostic = $event->payload['diagnostics'][strtolower($message->recipient_email)] ?? '';
            $detail = trim(($event->payload['detail'] ?? '') . ($diagnostic !== '' ? ' — ' . $diagnostic : ''));
            $state->update(['status' => $type, 'detail' => $detail ?: null, 'event_at' => $at]);
        }
    }
}
