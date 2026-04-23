<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityReauthLog extends Model
{
    protected $fillable = [
        'user_id',
        'method',
        'passed_at',
        'expires_at',
        'request_ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'passed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
