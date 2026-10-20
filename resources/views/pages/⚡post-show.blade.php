<?php

use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public const RELATED_LIMIT = 3;

    #[Locked]
    public Post $post;

    public function mount(Post $post): void
    {
        abort_unless($post->isPublished(), 404);

        $this->post = $post;
    }

    /**
     * Get other published posts in the same category, newest first.
     *
     * @return Collection<int, Post>
     */
    #[Computed]
    public function relatedPosts(): Collection
    {
        return Post::query()
            ->published()
            ->where('category', $this->post->category)
            ->whereKeyNot($this->post->id)
            ->latest('published_at')
            ->latest('id')
            ->limit(self::RELATED_LIMIT)
            ->get();
    }
};
?>

<div class="min-h-screen bg-stone-100 px-4 py-10 sm:py-16">
    <article class="mx-auto max-w-3xl rounded-lg bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200 sm:px-12 sm:py-12">
        <header class="space-y-3">
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a
                    href="{{ route('posts.index') }}"
                    class="text-sm text-stone-500 hover:text-stone-900"
                >&larr; All posts</a>

                <livewire:post-search />
            </div>
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-stone-500">
                <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $post->category->label() }}</span>
                <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('F j, Y') }}</time>
                <span aria-hidden="true">·</span>
                <span>{{ $post->readingTime() }} min read</span>
            </p>
            <h1 class="text-3xl font-semibold leading-tight text-stone-900 sm:text-4xl">{{ $post->title }}</h1>
            <p class="text-sm text-stone-500">By {{ $post->author_name }}</p>
        </header>

        <hr class="my-6 border-stone-200">

        <div class="space-y-5 text-base leading-7 text-stone-700">
            <p class="text-lg leading-8 text-stone-600">{{ $post->excerpt }}</p>

            @foreach ($post->paragraphs() as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        @if ($this->relatedPosts->isNotEmpty())
            <section
                aria-labelledby="related-heading"
                class="mt-12 border-t border-stone-200 pt-8"
            >
                <h2
                    id="related-heading"
                    class="text-sm font-semibold uppercase tracking-wide text-stone-500"
                >More in {{ $post->category->label() }}</h2>
                <ul class="mt-4 divide-y divide-stone-200">
                    @foreach ($this->relatedPosts as $relatedPost)
                        <li
                            wire:key="related-{{ $relatedPost->id }}"
                            class="flex items-baseline justify-between gap-4 py-3"
                        >
                            <a
                                href="{{ route('posts.show', $relatedPost) }}"
                                class="font-medium text-stone-800 hover:underline hover:underline-offset-2"
                            >{{ $relatedPost->title }}</a>
                            <time
                                datetime="{{ $relatedPost->published_at->toDateString() }}"
                                class="shrink-0 text-xs text-stone-400"
                            >{{ $relatedPost->published_at->format('M j, Y') }}</time>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
</div>
