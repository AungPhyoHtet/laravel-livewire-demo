<?php

use App\Enums\PostCategory;
use App\Models\Post;
use Carbon\CarbonImmutable;

test('a post casts its category and published date', function () {
    $post = Post::factory()->create([
        'category' => PostCategory::Design,
        'published_at' => '2026-05-04 09:00:00',
    ]);

    expect($post->fresh())
        ->category->toBe(PostCategory::Design)
        ->published_at->toBeInstanceOf(CarbonImmutable::class);
});

test('the published scope only includes posts whose published date has passed', function () {
    $published = Post::factory()->create(['published_at' => now()->subMinute()]);
    Post::factory()->create(['published_at' => now()->addDay()]);
    Post::factory()->draft()->create();

    expect(Post::published()->pluck('id')->all())->toBe([$published->id]);
});

test('a post knows whether it is published', function (?string $publishedAt, bool $isPublished) {
    $post = Post::factory()->make(['published_at' => $publishedAt ? now()->modify($publishedAt) : null]);

    expect($post->isPublished())->toBe($isPublished);
})->with([
    'in the past' => ['-1 day', true],
    'in the future' => ['+1 day', false],
    'draft' => [null, false],
]);

test('the body is split into paragraphs on blank lines', function () {
    $post = Post::factory()->make(['body' => "First paragraph.\n\nSecond\nstill second.\n\n\n\nThird.\n\n"]);

    expect($post->paragraphs())->toBe(['First paragraph.', "Second\nstill second.", 'Third.']);
});

test('reading time is the minutes at 200 words a minute, at least one', function (int $words, int $minutes) {
    $post = Post::factory()->make(['body' => trim(str_repeat('word ', $words))]);

    expect($post->readingTime())->toBe($minutes);
})->with([
    'short' => [10, 1],
    'exactly 200' => [200, 1],
    'just over' => [201, 2],
    'long' => [1000, 5],
]);
