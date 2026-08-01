<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ButtonActionJob extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEVICE_OFFLINE = 'device_offline';

    public const STATUS_TIMED_OUT = 'timed_out';

    public const STATUS_UNAUTHORIZED = 'unauthorized';

    public const STATUS_CANCELED = 'canceled';

    public const SOURCE_BUTTON = 'button';

    public const TERMINAL_STATUSES = [
        self::STATUS_SUCCEEDED,
        self::STATUS_FAILED,
        self::STATUS_DEVICE_OFFLINE,
        self::STATUS_TIMED_OUT,
        self::STATUS_UNAUTHORIZED,
        self::STATUS_CANCELED,
    ];

    protected $fillable = [
        'public_id',
        'user_id',
        'request_id',
        'button_page_item_id',
        'device_id',
        'product_function_id',
        'status',
        'source',
        'payload',
        'progress',
        'progress_message',
        'result',
        'error_code',
        'error_message',
        'locked_by_device_id',
        'lease_expires_at',
        'started_at',
        'finished_at',
        'front_end_timeout_at',
        'last_progress_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'progress' => 'integer',
            'lease_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'front_end_timeout_at' => 'datetime',
            'last_progress_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ButtonActionJob $job): void {
            $job->public_id ??= self::newPublicId();
        });
    }

    public static function newPublicId(): string
    {
        do {
            $publicId = 'BAJ-'.Str::upper(Str::random(12));
        } while (self::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function button(): BelongsTo
    {
        return $this->belongsTo(ButtonPageItem::class, 'button_page_item_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function lockedByDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'locked_by_device_id');
    }

    public function productFunction(): BelongsTo
    {
        return $this->belongsTo(ProductFunction::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ButtonActionJobEvent::class);
    }
}
