<?php

namespace Database\Seeders;

use App\Enums\PostCategory;
use App\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    /**
     * Sample post titles grouped by category.
     *
     * @var array<string, array<int, string>>
     */
    protected const TITLES = [
        'engineering' => [
            'Shipping Livewire 4 islands to production',
            'How we cut our test suite from 9 minutes to 90 seconds',
            'Debugging N+1 queries with Laravel Telescope',
            'Why we moved our queues to Redis',
            'A practical guide to database indexes',
            'Zero-downtime migrations on a busy table',
            'Writing Pest tests your teammates will enjoy reading',
            'Caching strategies that actually paid off',
        ],
        'design' => [
            'Designing calm interfaces for busy people',
            'Our colour system, explained',
            'The case for fewer modals',
            'Typography choices for long-form reading',
            'Accessible focus states without the ugliness',
            'Designing empty states that help',
            'What we learned from 40 usability sessions',
            'Motion design: fade, don’t bounce',
        ],
        'product' => [
            'Introducing autosave everywhere',
            'How we decide what not to build',
            'Writing specs before code: a year in',
            'Pricing changes for small teams',
            'The roadmap for the next six months',
            'Measuring what matters in onboarding',
            'Saying goodbye to the legacy editor',
            'Behind the new search experience',
        ],
        'culture' => [
            'How we run remote retrospectives',
            'Four-day weeks: six months later',
            'Hiring without whiteboard interviews',
            'Our approach to on-call',
            'Writing as a team superpower',
            'What a good first week looks like',
            'Keeping meetings short and useful',
            'Learning budgets and how we spend them',
        ],
        'news' => [
            'We raised our Series A',
            'Welcome to our new office in Lisbon',
            'Our 2026 open source sponsorships',
            'Announcing the community forum',
            'Laravel conference recap',
            'Status page and incident history now public',
            'New integrations with Slack and Linear',
            'Year in review',
        ],
    ];

    /**
     * Seed 40 published posts spread over the past year, plus 2 drafts.
     */
    public function run(): void
    {
        $authors = ['Alex Rivera', 'Priya Shah', 'Tom Becker', 'Mina Okafor', 'Sam Lee'];
        $index = 0;

        foreach (self::TITLES as $category => $titles) {
            foreach ($titles as $title) {
                Post::factory()->create([
                    'title' => $title,
                    'slug' => Str::slug($title),
                    'category' => PostCategory::from($category),
                    'author_name' => $authors[$index % count($authors)],
                    'published_at' => now()->subDays(($index * 37) % 365 + 1)->setTime(9, 0),
                ]);

                $index++;
            }
        }

        Post::factory()->draft()->count(2)->create();
    }
}
