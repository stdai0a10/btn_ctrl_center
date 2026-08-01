<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ButtonPage extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'name',
        'layout_columns',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'layout_columns' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ButtonPage $page): void {
            $page->public_id ??= self::newPublicId();
        });
    }

    public static function newPublicId(): string
    {
        do {
            $publicId = 'BPG-'.Str::upper(Str::random(12));
        } while (self::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ButtonPageItem::class)->orderBy('position');
    }
}
