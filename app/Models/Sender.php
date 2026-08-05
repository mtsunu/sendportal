<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sendportal\Base\Models\BaseModel;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $label
 * @property string $from_name
 * @property string $from_email
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Workspace $workspace
 */
class Sender extends BaseModel
{
    protected $fillable = [
        'label',
        'from_name',
        'from_email',
    ];

    /**
     * Normalize the identity fields used by create, update, and future capture flows.
     *
     * @param array<string, mixed> $input
     * @return array{label: string, from_name: string, from_email: string}
     */
    public static function normalizeInput(array $input): array
    {
        return [
            'label' => trim((string) ($input['label'] ?? '')),
            'from_name' => trim((string) ($input['from_name'] ?? '')),
            'from_email' => strtolower(trim((string) ($input['from_email'] ?? ''))),
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
