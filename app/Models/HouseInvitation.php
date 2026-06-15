<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseInvitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'house_id',
        'inviter_user_id',
        'invitee_user_id',
        'status',
        'cancelled_at',
        'accepted_at',
        'ignored_at',
    ];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'accepted_at' => 'datetime',
            'ignored_at' => 'datetime',
        ];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }
}
