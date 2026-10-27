<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessSesDeliveryEvent;
use App\Models\SesDeliveryEvent;
use Illuminate\Console\Command;

class ProcessSesDeliveryEvents extends Command
{
    protected $signature = 'ses:process-events {--limit=500}';

    protected $description = 'Recover unprocessed SES event inbox entries without resending email';

    public function handle(): int
    {
        SesDeliveryEvent::query()->whereNull('processed_at')->where('created_at', '>=', now()->subDays(30))
            ->orderBy('updated_at')->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))))
            ->get()->each(function (SesDeliveryEvent $event): void {
                ProcessSesDeliveryEvent::dispatch($event->id);
                $event->touch();
            });

        return self::SUCCESS;
    }
}
