<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomJoinRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'room_id',
        'requester_user_id',
        'status',
        'approved_by_user_id',
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
