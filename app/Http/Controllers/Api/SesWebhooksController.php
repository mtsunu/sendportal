<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessSesDeliveryEvent;
use App\Services\Ses\StoreDeliveryEvent;
use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Sendportal\Base\Http\Controllers\Api\Webhooks\SesWebhooksController as CoreController;
use Throwable;

class SesWebhooksController extends CoreController
{
    public function handle(): Response
    {
        $topics = config('sendportal-ses-events.topics', []);
        abort_unless($topics, 503, 'SES event receiver is not configured.');
        abort_if(strlen(request()->getContent()) > 1048576, 413);

        try {
            $message = Message::fromJsonString(request()->getContent());
        } catch (InvalidArgumentException | RuntimeException $exception) {
            abort(400, 'Invalid SNS envelope.');
        }

        foreach (['Type', 'Message', 'MessageId', 'TopicArn', 'Timestamp', 'Signature', 'SignatureVersion', 'SigningCertURL'] as $field) {
            abort_unless(is_string($message[$field]), 400, 'Invalid SNS envelope field.');
        }

        abort_unless(in_array($message['Type'], ['Notification', 'SubscriptionConfirmation', 'UnsubscribeConfirmation'], true), 400);

        $topic = $message['TopicArn'];
        abort_unless(is_string($topic) && isset($topics[$topic]) && $topics[$topic] > 0, 403);
        abort_unless(preg_match('/^arn:(aws|aws-cn|aws-us-gov):sns:([a-z0-9-]+):(\d{12}):[A-Za-z0-9_-]+$/', $topic, $parts), 403);

        try {
            app(MessageValidator::class)->validate($message);
        } catch (InvalidSnsMessageException $exception) {
            abort(403, 'Invalid SNS signature.');
        }

        if ($message['Type'] === 'SubscriptionConfirmation') {
            $host = 'sns.' . $parts[2] . '.amazonaws.com' . ($parts[1] === 'aws-cn' ? '.cn' : '');
            $url = (string) $message['SubscribeURL'];
            $parsed = parse_url($url);
            abort_unless(($parsed['scheme'] ?? '') === 'https' && ($parsed['host'] ?? '') === $host
                && ! isset($parsed['user']) && ! isset($parsed['pass']) && ! isset($parsed['port'])
                && ($parsed['path'] ?? '/') === '/', 403);
            $confirmation = Http::timeout(10)->withoutRedirecting()->get($url);
            abort_unless($confirmation->successful(), 503, 'SNS subscription confirmation failed.');

            return response('Subscription confirmed.');
        }

        if ($message['Type'] !== 'Notification') {
            return response('Ignored.');
        }

        $event = json_decode((string) $message['Message'], true);
        abort_unless(is_array($event), 400, 'Invalid SES event.');
        abort_unless(($event['mail']['sendingAccountId'] ?? null) === $parts[3], 403);
        $stored = app(StoreDeliveryEvent::class)->handle((int) $topics[$topic], $parts[2], $event);

        if ($stored && ! $stored->processed_at) {
            try {
                ProcessSesDeliveryEvent::dispatch($stored->id);
            } catch (Throwable $exception) {
                // The durable inbox is recovered by the scheduler if the queue is unavailable.
                Log::warning('SES event persisted; queue dispatch deferred.', ['event_id' => $stored->id]);
            }
        }

        return response('Accepted.');
    }
}
