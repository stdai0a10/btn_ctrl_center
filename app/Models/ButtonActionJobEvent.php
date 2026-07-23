<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ButtonActionJobEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'button_action_job_id',
        'type',
        'message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ButtonActionJob::class, 'button_action_job_id');
    }
}
