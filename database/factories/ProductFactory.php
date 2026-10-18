<?php

namespace Database\Factories;

use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'category' => fake()->randomElement(ProductCategory::cases()),
            'description' => fake()->optional()->sentence(12),
            'price' => fake()->randomFloat(2, 1, 500),
            'url' => fake()->optional()->url(),
        ];
    }
}
