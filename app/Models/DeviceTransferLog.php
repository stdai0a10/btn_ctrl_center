<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTransferLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'from_house_id',
        'to_house_id',
        'transferred_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function fromHouse(): BelongsTo
    {
        return $this->belongsTo(House::class, 'from_house_id');
    }

    public function toHouse(): BelongsTo
    {
        return $this->belongsTo(House::class, 'to_house_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }
}
