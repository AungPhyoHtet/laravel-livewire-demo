<?php

use App\Enums\PostCategory;
use App\Models\Post;
use Livewire\Livewire;

/**
 * Get the plain titles of the results, in order.
 *
 * @return array<int, string>
 */
function resultTitles(array $results): array
{
    return array_map(fn (array $result): string => strip_tags($result['title']->toHtml()), $results);
}

test('the posts list and the post page show the quick search trigger', function () {
    $post = Post::factory()->create();

    $this->get(route('posts.index'))->assertOk()->assertSeeLivewire('post-search');
    $this->get(route('posts.show', $post))->assertOk()->assertSeeLivewire('post-search');
});

test('the search opens with ⌘K, Ctrl K or / and is navigated with the keyboard', function () {
    Livewire::test('post-search')
        ->assertSee('Quick search…')
        ->assertSeeHtml('x-on:keydown.window="handleShortcut($event)"')
        ->assertSeeHtml("event.key.toLowerCase() === 'k'")
        ->assertSeeHtml("event.key === '/'")
        ->assertSeeHtml('x-on:keydown.arrow-down.prevent="move(1)"')
        ->assertSeeHtml('x-on:keydown.arrow-up.prevent="move(-1)"')
        ->assertSeeHtml('x-on:keydown.enter.prevent="choose()"')
        ->assertSeeHtml('x-on:keydown.escape.prevent.stop="close()"')
        ->assertSeeHtml('role="combobox"')
        ->assertSeeHtml('aria-modal="true"');
});

test('nothing is searched until something is typed', function () {
    Post::factory()->create(['title' => 'A post']);

    $page = Livewire::test('post-search')
        ->assertSee('Type to search post titles, excerpts and text.')
        ->assertDontSee('A post')
        ->set('query', '   ')
        ->assertSee('Type to search post titles, excerpts and text.');

    expect($page->get('results'))->toBe([]);
});

test('typing searches titles, excerpts and body text, with title matches first', function () {
    Post::factory()->create(['title' => 'Unrelated', 'excerpt' => 'Nothing here.', 'body' => 'Nothing here either.']);
    Post::factory()->create(['title' => 'Body match', 'excerpt' => 'A summary.', 'body' => 'We talk about caching at length.', 'published_at' => now()->subDay()]);
    Post::factory()->create(['title' => 'Excerpt match', 'excerpt' => 'All about caching.', 'body' => 'Plain body.', 'published_at' => now()->subDays(2)]);
    Post::factory()->create(['title' => 'Caching in practice', 'excerpt' => 'A summary.', 'body' => 'Plain body.', 'published_at' => now()->subDays(3)]);
    Post::factory()->draft()->create(['title' => 'Caching draft']);

    $page = Livewire::test('post-search')->set('query', '  caching ');

    expect(resultTitles($page->get('results')))->toBe(['Caching in practice', 'Body match', 'Excerpt match'])
        ->and($page->get('total'))->toBe(3);

    $page->assertSee('3 results');
});

test('matches are highlighted and escaped', function () {
    Post::factory()->create(['title' => 'Testing <script> tags', 'excerpt' => 'How we test.']);

    Livewire::test('post-search')
        ->set('query', 'test')
        ->assertSeeHtml('<mark class="rounded-sm bg-amber-100 text-inherit">Test</mark>ing &lt;script&gt; tags')
        ->assertSeeHtml('How we <mark class="rounded-sm bg-amber-100 text-inherit">test</mark>.');
});

test('a body-only match shows the matching part of the body', function () {
    Post::factory()->create([
        'title' => 'Release notes',
        'excerpt' => 'What shipped this week.',
        'body' => str_repeat('Filler words go here. ', 10)."The new websocket layer is faster.\n\n".str_repeat('More filler. ', 10),
    ]);

    $page = Livewire::test('post-search')->set('query', 'websocket');

    $snippet = $page->get('results')[0]['snippet']->toHtml();

    expect($snippet)->toStartWith('…')
        ->toContain('The new <mark class="rounded-sm bg-amber-100 text-inherit">websocket</mark> layer is faster.')
        ->toEndWith('…')
        ->not->toContain('What shipped this week.');
});

test('results are grouped by category and indexed in display order for the arrow keys', function () {
    Post::factory()->create(['title' => 'Zebra design one', 'category' => PostCategory::Design, 'published_at' => now()->subDays(1)]);
    Post::factory()->create(['title' => 'Zebra engineering', 'category' => PostCategory::Engineering, 'published_at' => now()->subDays(2)]);
    Post::factory()->create(['title' => 'Zebra design two', 'category' => PostCategory::Design, 'published_at' => now()->subDays(3)]);

    $page = Livewire::test('post-search')->set('query', 'zebra');

    expect($page->get('resultGroups')->map(fn ($results) => $results->pluck('index')->all())->all())->toBe([
        'Design' => [0, 1],
        'Engineering' => [2],
    ]);

    $page->assertSeeHtmlInOrder([
        'Design',
        'id="post-search-option-0"', 'design one',
        'id="post-search-option-1"', 'design two',
        'Engineering',
        'id="post-search-option-2"', 'engineering',
    ]);
});

test('results link to the post', function () {
    $post = Post::factory()->create(['title' => 'Linked post']);

    Livewire::test('post-search')
        ->set('query', 'linked')
        ->assertSeeHtml('href="'.route('posts.show', $post).'"');
});

test('more matches than shown offer a link to all results on the posts list', function () {
    Post::factory()->count(10)->create(['title' => 'Roadmap update']);

    $page = Livewire::test('post-search')->set('query', 'roadmap');

    expect($page->get('results'))->toHaveCount(8);

    $page->assertSee('See all 10 results in Posts')
        ->assertSeeHtml('id="post-search-option-8"')
        ->assertSeeHtml('href="'.e(route('posts.index', ['q' => 'roadmap'])).'"');
});

test('no matches shows an empty message', function () {
    Post::factory()->create(['title' => 'Something']);

    Livewire::test('post-search')
        ->set('query', 'zzz')
        ->assertSee('No posts match “zzz”.')
        ->assertDontSeeHtml('role="listbox"');
});

test('like wildcards in the search are matched literally', function () {
    Post::factory()->create(['title' => '100% uptime']);
    Post::factory()->create(['title' => '100 days of code']);

    $page = Livewire::test('post-search')->set('query', '100%');

    expect(resultTitles($page->get('results')))->toBe(['100% uptime']);
});
