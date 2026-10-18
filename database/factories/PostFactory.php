<?php

namespace Database\Factories;

use App\Enums\PostCategory;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(6), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 999999),
            'category' => fake()->randomElement(PostCategory::cases()),
            'excerpt' => fake()->sentence(20),
            'body' => implode("\n\n", fake()->paragraphs(fake()->numberBetween(4, 8))),
            'author_name' => fake()->name(),
            'published_at' => fake()->dateTimeBetween('-1 year', '-1 hour'),
        ];
    }

    /**
     * Indicate that the post has not been published.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }
}
