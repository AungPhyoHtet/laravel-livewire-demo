<?php

use App\Models\Product;
use App\Models\Tag;
use Database\Seeders\ProductSeeder;
use Database\Seeders\TagSeeder;
use Livewire\Livewire;

test('the page shows the product and its tags', function () {
    $product = Product::factory()->create(['name' => 'Desk lamp']);
    $product->tags()->attach(Tag::factory()->create(['name' => 'lighting']));

    $this->get(route('products.tags', $product))
        ->assertOk()
        ->assertSee('Desk lamp')
        ->assertSee('lighting')
        ->assertSee('aria-label="Remove lighting"', false)
        ->assertSee('1 / 10 tags')
        ->assertSee(route('products.index'));
});

test('the tag input follows the combobox pattern', function () {
    $product = Product::factory()->create();

    $this->get(route('products.tags', $product))
        ->assertSee('role="combobox"', false)
        ->assertSee('aria-controls="tag-suggestions"', false)
        ->assertSee('x-bind:aria-activedescendant', false)
        ->assertSee('wire:model.live.debounce.250ms="query"', false);
});

test('a missing product returns 404', function () {
    $this->get('/products/999/tags')->assertNotFound();
});

test('adding a new tag creates it, attaches it and shows a success toast', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::product-tags', ['product' => $product])
        ->set('query', 'Gift   Idea')
        ->call('addTag', '  Gift   Idea ')
        ->assertHasNoErrors()
        ->assertSet('query', '')
        ->assertDispatched('toast', type: 'success', message: 'Added “gift idea”.')
        ->assertSee('gift idea');

    expect($product->tags()->pluck('name')->all())->toBe(['gift idea']);
});

test('adding a tag that already exists reuses it regardless of case', function () {
    $product = Product::factory()->create();
    $tag = Tag::factory()->create(['name' => 'audio']);

    Livewire::test('pages::product-tags', ['product' => $product])
        ->call('addTag', 'AUDIO')
        ->assertDispatched('toast', type: 'success', message: 'Added “audio”.');

    expect(Tag::count())->toBe(1)
        ->and($product->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
});

test('adding a tag the product already has shows a warning and does not duplicate it', function () {
    $product = Product::factory()->create();
    $product->tags()->attach(Tag::factory()->create(['name' => 'audio']));

    Livewire::test('pages::product-tags', ['product' => $product])
        ->set('query', 'Audio')
        ->call('addTag', 'Audio')
        ->assertSet('query', '')
        ->assertDispatched('toast', type: 'warning', message: '“audio” is already added.');

    expect($product->tags()->count())->toBe(1);
});

test('adding an empty tag does nothing', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::product-tags', ['product' => $product])
        ->call('addTag', '   ')
        ->assertHasNoErrors()
        ->assertNotDispatched('toast');

    expect(Tag::count())->toBe(0);
});

test('an invalid tag is not saved and shows an error toast', function (string $name, string $message) {
    $product = Product::factory()->create();

    Livewire::test('pages::product-tags', ['product' => $product])
        ->call('addTag', $name)
        ->assertHasErrors('query')
        ->assertDispatched('toast', type: 'error', message: $message)
        ->assertSee($message);

    expect(Tag::count())->toBe(0);
})->with([
    'too long' => [str_repeat('a', 31), 'Tags can be at most 30 characters.'],
    'contains a comma' => ['red, blue', 'Tags can’t contain commas.'],
]);

test('a product can have at most 10 tags', function () {
    $product = Product::factory()->create();
    $product->tags()->attach(Tag::factory()->count(10)->create());

    Livewire::test('pages::product-tags', ['product' => $product])
        ->call('addTag', 'one too many')
        ->assertHasErrors('query')
        ->assertDispatched('toast', type: 'error', message: 'A product can have up to 10 tags.');

    expect($product->tags()->count())->toBe(10)
        ->and(Tag::where('name', 'one too many')->exists())->toBeFalse();
});

test('a successful add clears a previous error', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::product-tags', ['product' => $product])
        ->call('addTag', str_repeat('a', 31))
        ->assertHasErrors('query')
        ->call('addTag', 'valid')
        ->assertHasNoErrors();
});

test('removing a tag detaches it, keeps the tag and shows an info toast', function () {
    $product = Product::factory()->create();
    $tag = Tag::factory()->create(['name' => 'audio']);
    $product->tags()->attach($tag);

    Livewire::test('pages::product-tags', ['product' => $product])
        ->call('removeTag', $tag->id)
        ->assertDispatched('toast', type: 'info', message: 'Removed “audio”.')
        ->assertDontSee('aria-label="Remove audio"', false);

    expect($product->tags()->count())->toBe(0)
        ->and($tag->fresh())->not->toBeNull();
});

test('removing a tag only affects this product', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $tag = Tag::factory()->create();
    $otherProduct->tags()->attach($tag);

    Livewire::test('pages::product-tags', ['product' => $product])
        ->call('removeTag', $tag->id)
        ->assertNotDispatched('toast');

    expect($otherProduct->tags()->count())->toBe(1);
});

test('suggestions contain the typed text, skip attached tags and list prefix matches first', function () {
    $product = Product::factory()->create();
    $product->tags()->attach(Tag::factory()->create(['name' => 'outdoor']));
    Tag::factory()->createMany([
        ['name' => 'waterproof'],
        ['name' => 'door mat'],
        ['name' => 'doorbell'],
        ['name' => 'kitchen'],
    ]);

    $page = Livewire::test('pages::product-tags', ['product' => $product])
        ->set('query', ' DOOR ');

    expect($page->get('suggestions')->pluck('name')->all())->toBe(['door mat', 'doorbell']);

    $page->set('query', 'oo');

    expect($page->get('suggestions')->pluck('name')->all())->toBe(['door mat', 'doorbell', 'waterproof']);
});

test('suggestions are limited to six', function () {
    $product = Product::factory()->create();
    Tag::factory()->count(8)->sequence(fn ($sequence) => ['name' => "tag {$sequence->index}"])->create();

    $page = Livewire::test('pages::product-tags', ['product' => $product])
        ->set('query', 'tag');

    expect($page->get('suggestions'))->toHaveCount(6);
});

test('wildcard characters in the typed text are matched literally', function () {
    $product = Product::factory()->create();
    Tag::factory()->createMany([['name' => '100% cotton'], ['name' => 'audio'], ['name' => 'snake_case']]);

    $page = Livewire::test('pages::product-tags', ['product' => $product]);

    expect($page->set('query', '%')->get('suggestions')->pluck('name')->all())->toBe(['100% cotton'])
        ->and($page->set('query', '_')->get('suggestions')->pluck('name')->all())->toBe(['snake_case']);
});

test('the suggestions list shows matches and a hint for a new tag', function () {
    $product = Product::factory()->create();
    Tag::factory()->create(['name' => 'audio']);

    Livewire::test('pages::product-tags', ['product' => $product])
        ->assertDontSee('id="tag-suggestions"', false)
        ->set('query', 'aud')
        ->assertSee('data-name="audio"', false)
        ->assertSee('Press Enter to add “aud”')
        ->set('query', 'audio')
        ->assertDontSee('Press Enter to add');
});

test('the seeder tags the sample products', function () {
    $this->seed([ProductSeeder::class, TagSeeder::class]);

    expect(Product::firstWhere('name', 'Noise-cancelling headphones')->tags->pluck('name')->all())
        ->toBe(['audio', 'wireless', 'travel'])
        ->and(Tag::count())->toBe(13);
});

test('a tag error stays visible after the next request', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::product-tags', ['product' => $product])
        ->call('addTag', str_repeat('a', 31))
        ->call('$refresh')
        ->assertHasErrors('query')
        ->assertSee('Tags can be at most 30 characters.');
});

test('the new tag hint is hidden for a name that would be rejected', function (string $query) {
    $product = Product::factory()->create();

    Livewire::test('pages::product-tags', ['product' => $product])
        ->set('query', $query)
        ->assertDontSee('Press Enter to add');
})->with([
    'too long' => [str_repeat('a', 31)],
    'contains a comma' => ['red, blue'],
]);
