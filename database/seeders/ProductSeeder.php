<?php

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::factory()->create([
            'name' => 'Noise-cancelling headphones',
            'category' => ProductCategory::Electronics,
            'description' => 'Over-ear wireless headphones with 30-hour battery life.',
            'price' => '249.00',
            'url' => 'https://example.com/headphones',
        ]);

        Product::factory()->create([
            'name' => 'Cast iron skillet',
            'category' => ProductCategory::HomeAndKitchen,
            'description' => 'Pre-seasoned 12-inch skillet for stovetop and oven.',
            'price' => '39.95',
            'url' => null,
        ]);

        Product::factory()->create([
            'name' => 'Trail running shoes',
            'category' => ProductCategory::Sports,
            'description' => null,
            'price' => '129.50',
            'url' => 'https://example.com/trail-shoes',
        ]);
    }
}
