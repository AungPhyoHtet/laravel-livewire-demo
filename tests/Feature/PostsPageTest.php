<?php

use App\Enums\PostCategory;
use App\Models\Post;
use Database\Seeders\PostSeeder;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Create published posts whose titles end in their age order, newest first: "Post 1" is the newest.
 */
function postsNewestFirst(int $count, array $attributes = []): void
{
    foreach (range(1, $count) as $number) {
        Post::factory()->create([
            'title' => "Post {$number}",
            'published_at' => now()->subDays($number),
            ...$attributes,
        ]);
    }
}

/**
 * Get the island fragments rendered by the last request, which are sent apart from the component's HTML.
 */
function islandFragments(Testable $page): string
{
    return implode('', $page->effects['islandFragments'] ?? []);
}

test('the page lists published posts newest first', function () {
    Post::factory()->create(['title' => 'Older post', 'published_at' => '2026-09-01 09:00:00']);
    Post::factory()->create([
        'title' => 'Newer post',
        'category' => PostCategory::Design,
        'excerpt' => 'A short summary.',
        'author_name' => 'Priya Shah',
        'published_at' => '2026-10-10 09:00:00',
    ]);
    Post::factory()->draft()->create(['title' => 'Draft post']);
    Post::factory()->create(['title' => 'Scheduled post', 'published_at' => now()->addDay()]);

    $this->get(route('posts.index'))
        ->assertOk()
        ->assertSeeInOrder(['Newer post', 'Older post'])
        ->assertSee('Design')
        ->assertSee('A short summary.')
        ->assertSee('By Priya Shah')
        ->assertSee('Oct 10, 2026')
        ->assertSee('min read')
        ->assertSee(route('posts.show', Post::firstWhere('title', 'Newer post')))
        ->assertSee('2 posts')
        ->assertDontSee('Draft post')
        ->assertDontSee('Scheduled post');
});

test('the page shows the first 12 posts and a sentinel to load more', function () {
    postsNewestFirst(13);

    $page = Livewire::test('pages::posts');

    expect($page->get('posts'))->toHaveCount(12);

    $page->assertSee('Post 12')
        ->assertDontSee('Post 13')
        ->assertSeeHtml('wire:intersect.margin.300px="loadMore"')
        ->assertSee('Load more posts')
        ->assertDontSee('You’ve reached the end.');
});

test('loading more appends only the next page to the list island until the end', function () {
    postsNewestFirst(25);

    $page = Livewire::test('pages::posts')
        ->call('loadMore')
        ->assertSet('page', 2);

    expect(islandFragments($page))
        ->toContain('name=posts', 'mode=append', 'Post 13', 'Post 24')
        ->not->toContain('Post 12', 'Post 25');

    $page->call('loadMore')
        ->assertSet('page', 3)
        ->assertDontSeeHtml('wire:intersect')
        ->assertSee('You’ve reached the end.');

    expect(islandFragments($page))
        ->toContain('mode=append', 'Post 25')
        ->not->toContain('Post 24');
});

test('loading more at the end does nothing', function () {
    postsNewestFirst(3);

    $page = Livewire::test('pages::posts')
        ->call('loadMore')
        ->assertSet('page', 1);

    expect(islandFragments($page))->toBe('');
});

test('the sentinel gets a new key after each load so it is observed again', function () {
    postsNewestFirst(30);

    Livewire::test('pages::posts')
        ->assertSeeHtml('wire:key="sentinel-1"')
        ->call('loadMore')
        ->assertSeeHtml('wire:key="sentinel-2"');
});

test('searching filters posts by title or excerpt, ignoring case', function () {
    Post::factory()->create(['title' => 'Shipping Livewire islands', 'excerpt' => 'About islands.']);
    Post::factory()->create(['title' => 'Database indexes', 'excerpt' => 'Why LIVEWIRE apps need them.']);
    Post::factory()->create(['title' => 'Hiring', 'excerpt' => 'How we hire.']);

    $page = Livewire::test('pages::posts')
        ->set('search', '  livewire ');

    expect($page->get('posts')->pluck('title')->sort()->values()->all())
        ->toBe(['Database indexes', 'Shipping Livewire islands']);

    $page->assertSee('2 posts matching “livewire”');
});

test('wildcard characters in the search are matched literally', function () {
    Post::factory()->create(['title' => '100% uptime']);
    Post::factory()->create(['title' => 'snake_case names']);
    Post::factory()->create(['title' => 'Something else', 'excerpt' => 'Plain text.']);

    $page = Livewire::test('pages::posts');

    expect($page->set('search', '%')->get('posts')->pluck('title')->all())->toBe(['100% uptime'])
        ->and($page->set('search', '_')->get('posts')->pluck('title')->all())->toBe(['snake_case names']);
});

test('choosing a category shows only its posts', function () {
    Post::factory()->create(['title' => 'Design post', 'category' => PostCategory::Design]);
    Post::factory()->create(['title' => 'News post', 'category' => PostCategory::News]);

    $page = Livewire::test('pages::posts')
        ->set('category', 'design');

    expect($page->get('posts')->pluck('title')->all())->toBe(['Design post']);

    $page->set('category', '');

    expect($page->get('posts'))->toHaveCount(2);
});

test('search and category combine', function () {
    Post::factory()->create(['title' => 'Design systems', 'category' => PostCategory::Design]);
    Post::factory()->create(['title' => 'Design reviews', 'category' => PostCategory::Culture]);
    Post::factory()->create(['title' => 'Colour', 'excerpt' => 'Plain.', 'category' => PostCategory::Design]);

    $page = Livewire::test('pages::posts')
        ->set('search', 'design')
        ->set('category', 'design');

    expect($page->get('posts')->pluck('title')->all())->toBe(['Design systems']);
});

test('an unknown category is ignored', function () {
    postsNewestFirst(2);

    $page = Livewire::withQueryParams(['category' => 'nonsense'])->test('pages::posts');

    expect($page->get('posts'))->toHaveCount(2);
});

test('changing a filter re-renders the list island from the first page', function (string $property, string $value) {
    postsNewestFirst(30, ['category' => PostCategory::Design, 'excerpt' => 'Design notes.']);

    $page = Livewire::test('pages::posts')
        ->call('loadMore')
        ->assertSet('page', 2)
        ->set($property, $value)
        ->assertSet('page', 1);

    expect(islandFragments($page))
        ->toContain('name=posts', 'mode=morph', 'Post 1', 'Post 12')
        ->not->toContain('Post 13');
})->with([
    'search' => ['search', 'design'],
    'category' => ['category', 'design'],
]);

test('the filters are read from the url', function () {
    Post::factory()->create(['title' => 'Design systems', 'category' => PostCategory::Design]);
    Post::factory()->create(['title' => 'Design news', 'category' => PostCategory::News]);

    $this->get(route('posts.index', ['q' => 'design', 'category' => 'news']))
        ->assertOk()
        ->assertSee('Design news')
        ->assertDontSee('Design systems')
        ->assertSee('1 post matching “design”');
});

test('the quick search keeps its component when the list island renders', function () {
    postsNewestFirst(13);

    $page = Livewire::test('pages::posts');
    $searchComponentId = $page->snapshot['memo']['children']['post-search'][1];

    $page->call('loadMore')->set('search', 'post');

    expect($page->snapshot['memo']['children']['post-search'][1])->toBe($searchComponentId);
});

test('the filters are kept in the url as q and category', function () {
    $this->get(route('posts.index'))
        ->assertSee('&quot;search&quot;:{&quot;as&quot;:&quot;q&quot;', false)
        ->assertSee('&quot;category&quot;:{&quot;as&quot;:null', false);

    Livewire::withQueryParams(['q' => 'laravel', 'category' => 'engineering'])
        ->test('pages::posts')
        ->assertSet('search', 'laravel')
        ->assertSet('category', 'engineering');
});

test('the search and category controls are wired up', function () {
    $this->get(route('posts.index'))
        ->assertSee('wire:model.live.debounce.300ms="search"', false)
        ->assertSee('wire:model.live="category"', false)
        ->assertSee('All categories')
        ->assertSee('Engineering')
        ->assertSee('Culture');
});

test('nothing matching shows an empty state that clears the filters', function () {
    postsNewestFirst(2);

    Livewire::test('pages::posts')
        ->set('search', 'no such post')
        ->set('category', 'news')
        ->assertSee('0 posts matching “no such post”')
        ->assertSee('No posts match your filters.')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('category', '')
        ->assertDontSee('No posts match your filters.')
        ->tap(fn (Testable $page) => expect(islandFragments($page))->toContain('mode=morph', 'Post 1', 'Post 2'));
});

test('the page says when there are no posts at all', function () {
    $this->get(route('posts.index'))
        ->assertSee('No posts yet.')
        ->assertDontSee('Clear filters');
});

test('the seeder creates 200 published posts and 2 drafts', function () {
    $this->seed(PostSeeder::class);

    expect(Post::published()->count())->toBe(200)
        ->and(Post::count())->toBe(202)
        ->and(Post::firstWhere('slug', 'shipping-livewire-4-islands-to-production'))->not->toBeNull();
});

test('the last load hides the sentinel in the same response', function () {
    postsNewestFirst(13);

    Livewire::test('pages::posts')
        ->call('loadMore')
        ->assertDontSeeHtml('wire:intersect')
        ->assertSee('You’ve reached the end.');
});
