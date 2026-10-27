<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Ses\ApplyDeliveryEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSesDeliveryEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue(config('sendportal-ses-events.queue'));
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(ApplyDeliveryEvent $service): void
    {
        $service->handle($this->eventId);
    }
}
