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
        'product_id',
        'serial_number',
        'secret_hash',
        'current_room_id',
        'name',
        'is_locked',
        'is_enabled',
        'long_token_jti',
        'long_token_issued_at',
        'long_token_expires_at',
        'long_token_revoked_at',
        'token_version',
        'current_access_jti',
        'current_access_expires_at',
        'runner_status',
        'runner_current_job_id',
        'runner_last_seen_at',
        'runner_registered_at',
        'runner_disabled_at',
        'is_system_disabled',
        'system_disabled_at',
        'system_disabled_by_user_id',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'is_enabled' => 'boolean',
            'token_version' => 'integer',
            'long_token_issued_at' => 'datetime',
            'long_token_expires_at' => 'datetime',
            'long_token_revoked_at' => 'datetime',
            'current_access_expires_at' => 'datetime',
            'runner_last_seen_at' => 'datetime',
            'runner_registered_at' => 'datetime',
            'runner_disabled_at' => 'datetime',
            'is_system_disabled' => 'boolean',
            'system_disabled_at' => 'datetime',
        ];
    }

    public function currentRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'current_room_id')->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transferLogs(): HasMany
    {
        return $this->hasMany(DeviceTransferLog::class);
    }

    public function jwtTokens(): HasMany
    {
        return $this->hasMany(DeviceJwtToken::class);
    }

    public function buttonItems(): HasMany
    {
        return $this->hasMany(ButtonPageItem::class);
    }

    public function buttonActionJobs(): HasMany
    {
        return $this->hasMany(ButtonActionJob::class);
    }
}
