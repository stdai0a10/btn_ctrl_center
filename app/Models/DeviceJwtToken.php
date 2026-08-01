<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
            'metadata' => 'array',
        ];
    }

    protected function issuedAt(): Attribute
    {
        return $this->utcDateTimeAttribute();
    }

    protected function expiresAt(): Attribute
    {
        return $this->utcDateTimeAttribute();
    }

    protected function revokedAt(): Attribute
    {
        return $this->utcDateTimeAttribute();
    }

    protected function lastUsedAt(): Attribute
    {
        return $this->utcDateTimeAttribute();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    private function utcDateTimeAttribute(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?Carbon => $value === null
                ? null
                : Carbon::parse($value, 'UTC')->timezone(config('app.timezone')),
            set: fn ($value): ?string => $value === null
                ? null
                : Carbon::parse($value)->utc()->format('Y-m-d H:i:s'),
        );
    }
}
