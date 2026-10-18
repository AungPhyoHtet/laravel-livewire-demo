<?php

use App\Enums\PostCategory;
use App\Models\Post;

test('the page shows the post', function () {
    $post = Post::factory()->create([
        'title' => 'Designing calm interfaces',
        'slug' => 'designing-calm-interfaces',
        'category' => PostCategory::Design,
        'excerpt' => 'Less noise, more signal.',
        'body' => "First paragraph.\n\nSecond paragraph.",
        'author_name' => 'Priya Shah',
        'published_at' => '2026-10-10 09:00:00',
    ]);

    $this->get('/posts/designing-calm-interfaces')
        ->assertOk()
        ->assertSee('Designing calm interfaces')
        ->assertSee('Design')
        ->assertSee('Less noise, more signal.')
        ->assertSeeHtmlInOrder(['<p>First paragraph.</p>', '<p>Second paragraph.</p>'])
        ->assertSee('By Priya Shah')
        ->assertSee('October 10, 2026')
        ->assertSee('1 min read')
        ->assertSee(route('posts.index'));

    expect(route('posts.show', $post))->toEndWith('/posts/designing-calm-interfaces');
});

test('the page shows up to three other published posts in the same category', function () {
    $post = Post::factory()->create(['category' => PostCategory::Design]);
    foreach (range(1, 4) as $number) {
        Post::factory()->create([
            'title' => "Related {$number}",
            'category' => PostCategory::Design,
            'published_at' => now()->subDays($number),
        ]);
    }
    Post::factory()->create(['title' => 'Other category', 'category' => PostCategory::News]);
    Post::factory()->draft()->create(['title' => 'Draft design', 'category' => PostCategory::Design]);

    $this->get(route('posts.show', $post))
        ->assertSee('More in Design')
        ->assertSeeInOrder(['Related 1', 'Related 2', 'Related 3'])
        ->assertDontSee('Related 4')
        ->assertDontSee('Other category')
        ->assertDontSee('Draft design');
});

test('the related section is hidden when there are no other posts in the category', function () {
    $post = Post::factory()->create(['category' => PostCategory::News]);

    $this->get(route('posts.show', $post))
        ->assertDontSee('More in News');
});

test('a missing post returns 404', function () {
    $this->get('/posts/no-such-post')->assertNotFound();
});

test('a draft or scheduled post returns 404', function (?string $publishedAt) {
    $post = Post::factory()->create(['published_at' => $publishedAt ? now()->modify($publishedAt) : null]);

    $this->get(route('posts.show', $post))->assertNotFound();
})->with([
    'draft' => [null],
    'scheduled' => ['+1 day'],
]);
