<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'serial_number',
        'secret_hash',
        'current_house_id',
        'name',
        'is_locked',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
        ];
    }

    public function currentHouse(): BelongsTo
    {
        return $this->belongsTo(House::class, 'current_house_id');
    }

    public function transferLogs(): HasMany
    {
        return $this->hasMany(DeviceTransferLog::class);
    }
}
