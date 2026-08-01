<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ButtonPageItem extends Model
{
    public const SHAPE_ROUNDED_SQUARE = 'rounded_square';

    public const SHAPE_CIRCLE = 'circle';

    public const CONTENT_ICON = 'icon';

    public const CONTENT_TEXT = 'text';

    protected $fillable = [
        'public_id',
        'button_page_id',
        'device_id',
        'product_function_id',
        'position',
        'shape',
        'background_color',
        'content_type',
        'icon_key',
        'label',
        'foreground_color',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ButtonPageItem $item): void {
            $item->public_id ??= self::newPublicId();
        });
    }

    public static function newPublicId(): string
    {
        do {
            $publicId = 'BTN-'.Str::upper(Str::random(12));
        } while (self::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(ButtonPage::class, 'button_page_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function productFunction(): BelongsTo
    {
        return $this->belongsTo(ProductFunction::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ButtonActionJob::class);
    }
}
