<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesMessageStatus extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['event_at' => 'datetime', 'vdm_checked_at' => 'datetime'];
    }
}
