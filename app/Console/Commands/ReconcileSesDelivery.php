<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SesMessageStatus;
use App\Services\Ses\ReconcileDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\EmailServiceType;
use Sendportal\Base\Models\Message;
use Throwable;

class ReconcileSesDelivery extends Command
{
    protected $signature = 'ses:reconcile-delivery {--limit=25}';

    protected $description = 'Reconcile recent campaign delivery events from VDM, without resending emails';

    public function handle(ReconcileDelivery $reconciler): int
    {
        if (! config('sendportal-ses-events.reconcile_enabled')) {
            $this->warn('VDM reconciliation is disabled. Set SES_VDM_RECONCILE_ENABLED=true after verifying IAM access.');

            return self::SUCCESS;
        }

        $lock = Cache::lock('ses-vdm-reconcile', 600);

        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $messages = Message::query()->where('source_type', Campaign::class)
                ->whereNotNull('message_id')->where('sent_at', '>=', now()->subDays(30))
                ->whereNull('delivered_at')->whereNull('bounced_at')->whereNull('complained_at')
                ->join('sendportal_campaigns as campaign', function ($join): void {
                    $join->on('campaign.id', '=', 'sendportal_messages.source_id')
                        ->on('campaign.workspace_id', '=', 'sendportal_messages.workspace_id');
                })
                ->join('sendportal_email_services as provider', 'provider.id', '=', 'campaign.email_service_id')
                ->where('provider.type_id', EmailServiceType::SES)
                ->leftJoin('ses_message_statuses as state', 'state.sendportal_message_id', '=', 'sendportal_messages.id')
                ->where(function ($query): void {
                    $query->whereNull('state.vdm_checked_at')->orWhere('state.vdm_checked_at', '<', now()->subHour());
                })
                ->orderByRaw('CASE WHEN state.vdm_checked_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('state.vdm_checked_at')->orderBy('sendportal_messages.id')
                ->select('sendportal_messages.*')->limit(max(1, min(50, (int) $this->option('limit'))))->get();

            foreach ($messages as $index => $message) {
                if ($index > 0) {
                    usleep(1100000); // GetMessageInsights allows only one request per second.
                }

                $provider = Campaign::findOrFail($message->source_id)->email_service;
                $settings = $provider->settings;

                try {
                    $client = app('aws')->createClient('sesv2', [
                        'region' => $settings['region'],
                        'credentials' => ['key' => $settings['key'], 'secret' => $settings['secret']],
                        'http' => ['connect_timeout' => 3, 'timeout' => 5],
                        'retries' => 0,
                    ]);
                    $reconciler->handle($message, $client, $settings['region']);
                } catch (Throwable $exception) {
                    // Do not expose credentials, recipient addresses, or AWS request bodies.
                    $this->warn('VDM lookup failed for local message ' . $message->id . '; retry after checking IAM/region/availability.');
                    SesMessageStatus::updateOrCreate(['sendportal_message_id' => $message->id], [
                        'workspace_id' => $message->workspace_id, 'vdm_checked_at' => now(),
                    ]);
                }
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
