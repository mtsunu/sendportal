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

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
