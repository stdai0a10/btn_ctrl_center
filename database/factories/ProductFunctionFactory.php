<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFunction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFunction>
 */
class ProductFunctionFactory extends Factory
{
    protected $model = ProductFunction::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'code' => ProductFunction::newCode(),
            'description' => fake()->sentence(3),
        ];
    }
}
