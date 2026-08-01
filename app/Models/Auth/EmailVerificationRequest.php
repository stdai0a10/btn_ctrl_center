<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationRequest extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'purpose',
        'token_hash',
        'expires_at',
        'used_at',
        'invalidated_at',
        'request_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim($value));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
