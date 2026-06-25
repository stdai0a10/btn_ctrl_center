<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceJwtToken extends Model
{
    public const TYPE_LONG = 'device_long';

    public const TYPE_ACCESS = 'device_access';

    protected $fillable = [
        'jti',
        'device_id',
        'type',
        'token_version',
        'issued_at',
        'expires_at',
        'revoked_at',
        'last_used_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'token_version' => 'integer',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
