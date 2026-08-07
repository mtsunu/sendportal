<?php

declare(strict_types=1);

namespace App\Services\Senders;

use App\Models\Sender;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;
use RuntimeException;
use Sendportal\Base\Models\Campaign;

class CaptureCampaignSender
{
    public function __construct(
        private readonly ConnectionResolverInterface $connectionResolver,
    ) {
    }

    /**
     * Capture a campaign sender without changing the persisted campaign.
     */
    public function handle(Campaign $campaign): bool
    {
        $workspaceId = $campaign->getAttribute('workspace_id');

        if (! $workspaceId) {
            throw new RuntimeException('A campaign workspace is required for sender capture.');
        }

        $normalized = Sender::normalizeInput([
            'label' => $this->labelFor($campaign),
            'from_name' => $campaign->getAttribute('from_name'),
            'from_email' => $campaign->getAttribute('from_email'),
        ]);

        if ($normalized['from_name'] === '' || $normalized['from_email'] === '') {
            return false;
        }

        $payload = [
            'workspace_id' => $workspaceId,
            ...$normalized,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $connection = $this->connectionResolver->connection();
        $inserted = $connection->table('senders')->insertOrIgnore($payload);

        if ($inserted > 0) {
            return true;
        }

        $duplicateExists = $connection->table('senders')
            ->where('workspace_id', $workspaceId)
            ->where('from_name', $normalized['from_name'])
            ->where('from_email', $normalized['from_email'])
            ->exists();

        if ($duplicateExists) {
            return false;
        }

        throw new RuntimeException('Sender capture was ignored without a matching duplicate.');
    }

    private function labelFor(Campaign $campaign): string
    {
        $label = Str::substr(trim((string) $campaign->getAttribute('name')), 0, 255);

        return $label !== '' ? $label : 'Campaign sender';
    }
}
