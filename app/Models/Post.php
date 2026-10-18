<?php

namespace App\Models;

use App\Enums\PostCategory;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property PostCategory $category
 * @property string $excerpt
 * @property string $body
 * @property string $author_name
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'slug', 'category', 'excerpt', 'body', 'author_name', 'published_at'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public const WORDS_PER_MINUTE = 200;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PostCategory::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Limit the query to posts whose published date has passed.
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Determine whether the post's published date has passed.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * Get the body split into paragraphs on blank lines.
     *
     * @return array<int, string>
     */
    public function paragraphs(): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\R{2,}/', $this->body)),
            fn (string $paragraph): bool => $paragraph !== '',
        ));
    }

    /**
     * Get the estimated reading time in minutes, at least 1.
     */
    public function readingTime(): int
    {
        return max(1, (int) ceil(Str::wordCount($this->body) / self::WORDS_PER_MINUTE));
    }
}
