<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'public_id',
        'model_number',
        'name',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->public_id ??= self::newPublicId();
        });
    }

    public static function newPublicId(): string
    {
        do {
            $publicId = 'PRD-'.Str::upper(Str::random(12));
        } while (self::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function functions(): HasMany
    {
        return $this->hasMany(ProductFunction::class);
    }
}
