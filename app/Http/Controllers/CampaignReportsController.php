<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Sendportal\Base\Facades\Sendportal;
use Sendportal\Base\Http\Controllers\Campaigns\CampaignReportsController as CoreController;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\Message;

class CampaignReportsController extends CoreController
{
    public function recipients(int $id)
    {
        $workspaceId = Sendportal::currentWorkspaceId();
        $campaign = $this->campaignRepo->find($workspaceId, $id);

        if ($campaign->draft) {
            return redirect()->route('sendportal.campaigns.edit', $id);
        }

        if ($campaign->queued || $campaign->sending) {
            return redirect()->route('sendportal.campaigns.status', $id);
        }

        $validated = request()->validate(['email' => ['nullable', 'string', 'max:254']]);
        $email = trim($validated['email'] ?? '');
        $query = Message::query()->where('sendportal_messages.workspace_id', $workspaceId)
            ->where('source_type', Campaign::class)->where('source_id', $id)->whereNotNull('sent_at')
            ->leftJoin('ses_message_statuses', function ($join) use ($workspaceId): void {
                $join->on('ses_message_statuses.sendportal_message_id', '=', 'sendportal_messages.id')
                    ->where('ses_message_statuses.workspace_id', '=', $workspaceId);
            })
            ->select('sendportal_messages.*', 'ses_message_statuses.status as ses_status', 'ses_message_statuses.detail as ses_detail');

        if ($email !== '') {
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($email)) . '%';
            $query->whereRaw("LOWER(recipient_email) LIKE ? ESCAPE '!'", [$pattern]);
        }

        $messages = $query->orderBy('recipient_email')->orderBy('sendportal_messages.id')->paginate(50)->appends(['email' => $email]);

        return view('sendportal::campaigns.reports.recipients', compact('campaign', 'messages', 'email'));
    }
}
