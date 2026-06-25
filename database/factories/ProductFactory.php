<?php

namespace Database\Factories;

use App\Models\Product;
use App\Support\ProductModelNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'public_id' => Product::newPublicId(),
            'model_number' => ProductModelNumber::normalize('BTN-'.Str::upper(Str::random(8))),
            'name' => fake()->words(2, true),
            'is_locked' => false,
        ];
    }
}
