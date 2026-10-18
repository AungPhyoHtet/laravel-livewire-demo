<?php

use App\Enums\ProductCategory;
use App\Models\Product;

test('a product can be created with a name, category, description, price and url', function () {
    $product = Product::create([
        'name' => 'Desk lamp',
        'category' => ProductCategory::HomeAndKitchen,
        'description' => 'Warm LED lamp.',
        'price' => '49.9',
        'url' => 'https://example.com/lamp',
    ]);

    expect($product->fresh())
        ->name->toBe('Desk lamp')
        ->category->toBe(ProductCategory::HomeAndKitchen)
        ->description->toBe('Warm LED lamp.')
        ->price->toBe('49.90')
        ->url->toBe('https://example.com/lamp');
});

test('description and url are optional', function () {
    $product = Product::factory()->create(['description' => null, 'url' => null]);

    expect($product->fresh())
        ->description->toBeNull()
        ->url->toBeNull();
});

test('the price is formatted for display', function () {
    $product = Product::factory()->make(['price' => '1234.5']);

    expect($product->formattedPrice())->toBe('$1,234.50');
});
