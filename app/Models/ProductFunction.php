<?php

namespace App\Models;

use Database\Factories\ProductFunctionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductFunction extends Model
{
    /** @use HasFactory<ProductFunctionFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'code',
        'description',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductFunction $function): void {
            $function->code ??= self::newCode();
        });
    }

    public static function newCode(): string
    {
        do {
            $code = 'PFN-'.Str::upper(Str::random(12));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
