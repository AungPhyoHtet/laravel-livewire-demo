<?php

use App\Enums\ProductCategory;
use App\Models\Product;
use Livewire\Livewire;

test('the page lists products newest first', function () {
    Product::factory()->create(['name' => 'Older product', 'created_at' => now()->subDay()]);
    Product::factory()->create([
        'name' => 'Newer product',
        'category' => ProductCategory::Books,
        'price' => '1234.5',
        'url' => 'https://example.com/newer',
    ]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSeeInOrder(['Newer product', 'Older product'])
        ->assertSee('Books')
        ->assertSee('$1,234.50')
        ->assertSee('https://example.com/newer');
});

test('the page shows the create product button', function () {
    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('Create Product')
        ->assertSeeLivewire('create-product-wizard');
});

test('the page shows an empty state when there are no products', function () {
    $this->get(route('products.index'))
        ->assertSee('No products yet. Create your first one.');
});

test('the list refreshes when a product is created', function () {
    $page = Livewire::test('pages::products')
        ->assertSee('No products yet.');

    Product::factory()->create(['name' => 'Fresh product']);

    $page->call('$refresh')
        ->assertSee('Fresh product')
        ->assertDontSee('No products yet.');
});
