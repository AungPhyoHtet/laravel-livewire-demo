<?php

use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\UniqueConstraintViolationException;

test('tag names are unique', function () {
    Tag::factory()->create(['name' => 'audio']);

    Tag::factory()->create(['name' => 'audio']);
})->throws(UniqueConstraintViolationException::class);

test('a product returns its tags in the order they were added', function () {
    $product = Product::factory()->create();
    [$zebra, $apple, $mango] = Tag::factory()->createMany([
        ['name' => 'zebra'],
        ['name' => 'apple'],
        ['name' => 'mango'],
    ]);

    $product->tags()->attach($mango);
    $product->tags()->attach($zebra);
    $product->tags()->attach($apple);

    expect($product->tags->pluck('name')->all())->toBe(['mango', 'zebra', 'apple']);
});

test('a tag can be on many products', function () {
    $tag = Tag::factory()->create();
    $products = Product::factory()->count(2)->create();

    $tag->products()->attach($products);

    expect($tag->products)->toHaveCount(2);
});

test('deleting a product removes its tag links but keeps the tags', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $tag = Tag::factory()->create();
    $product->tags()->attach($tag);
    $otherProduct->tags()->attach($tag);

    $product->delete();

    expect($tag->fresh())->not->toBeNull()
        ->and($tag->products()->pluck('products.id')->all())->toBe([$otherProduct->id]);
});
