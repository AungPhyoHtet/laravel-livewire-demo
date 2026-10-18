<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Seed tags and attach them to the sample products.
     */
    public function run(): void
    {
        $tagsByProduct = [
            'Noise-cancelling headphones' => ['audio', 'wireless', 'travel'],
            'Cast iron skillet' => ['kitchen', 'cookware'],
            'Trail running shoes' => ['outdoor', 'running'],
        ];

        foreach ($tagsByProduct as $productName => $tagNames) {
            $product = Product::firstWhere('name', $productName);

            foreach ($tagNames as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);

                $product?->tags()->syncWithoutDetaching($tag);
            }
        }

        foreach (['bestseller', 'eco-friendly', 'gift idea', 'new arrival', 'sale', 'waterproof'] as $tagName) {
            Tag::firstOrCreate(['name' => $tagName]);
        }
    }
}
