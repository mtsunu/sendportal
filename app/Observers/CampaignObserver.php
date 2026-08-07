<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\Senders\CaptureCampaignSender;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Sendportal\Base\Models\Campaign;
use Throwable;

class CampaignObserver implements ShouldHandleEventsAfterCommit
{
    public const WARNING_ATTRIBUTE = 'sendportal.sender_capture_warning';

    private const CAPTURE_WARNING = 'The campaign was saved, but its sender could not be saved automatically.';

    public function __construct(private readonly CaptureCampaignSender $captureCampaignSender)
    {
    }

    public function created(Campaign $campaign): void
    {
        try {
            $this->captureCampaignSender->handle($campaign);
        } catch (Throwable $exception) {
            Log::warning('campaign_sender_auto_capture_failed', [
                'campaign_id' => $campaign->getKey(),
                'workspace_id' => $campaign->getAttribute('workspace_id'),
                'exception_class' => $exception::class,
                'exception_code' => (int) $exception->getCode(),
            ]);

            $request = request();

            if ($request->hasSession()) {
                $request->session()->flash('warning', self::CAPTURE_WARNING);
            } else {
                $request->attributes->set(self::WARNING_ATTRIBUTE, true);
            }
        }
    }
}
