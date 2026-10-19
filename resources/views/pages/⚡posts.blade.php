<?php

use App\Enums\PostCategory;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public const PAGE_SIZE = 12;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    public int $page = 1;

    public function updatedSearch(): void
    {
        $this->showFirstPage();
    }

    public function updatedCategory(): void
    {
        $this->showFirstPage();
    }

    /**
     * Append the next page of posts to the list island, so only the new rows are queried and sent.
     */
    public function loadMore(): void
    {
        if ($this->hasMore()) {
            $this->page++;

            $this->renderIsland('posts', mode: 'append');
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category');

        $this->showFirstPage();
    }

    /**
     * Start the list again from the first page.
     *
     * Islands are skipped when the rest of the component re-renders, so the list island is rendered explicitly.
     */
    protected function showFirstPage(): void
    {
        $this->page = 1;

        $this->renderIsland('posts');
    }

    /**
     * Get the published posts that match the search and category.
     *
     * @return Builder<Post>
     */
    protected function filteredQuery(): Builder
    {
        $search = Str::squish($this->search);
        $category = PostCategory::tryFrom($this->category);

        return Post::query()
            ->published()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

                $query->where(fn (Builder $query) => $query
                    ->whereRaw("title like ? escape '!'", [$pattern])
                    ->orWhereRaw("excerpt like ? escape '!'", [$pattern]));
            })
            ->when($category !== null, fn (Builder $query) => $query->where('category', $category));
    }

    /**
     * Get the current page of posts, newest first.
     *
     * @return Collection<int, Post>
     */
    #[Computed]
    public function posts(): Collection
    {
        return $this->filteredQuery()
            ->latest('published_at')
            ->latest('id')
            ->forPage($this->page, self::PAGE_SIZE)
            ->get();
    }

    #[Computed]
    public function total(): int
    {
        return $this->filteredQuery()->count();
    }

    /**
     * Determine whether more matching posts are waiting to be loaded.
     *
     * Not computed: it depends on page, which loadMore changes mid-request.
     */
    public function hasMore(): bool
    {
        return $this->total > $this->page * self::PAGE_SIZE;
    }

    /**
     * Get the sentence describing how many posts match.
     */
    #[Computed]
    public function summary(): string
    {
        $count = $this->total.' '.Str::plural('post', $this->total);
        $search = Str::squish($this->search);

        return $search === '' ? $count : "{$count} matching “{$search}”";
    }
};
?>

<div class="min-h-screen bg-stone-100 px-4 py-10 sm:py-16">
    <article class="mx-auto max-w-3xl rounded-lg bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200 sm:px-12 sm:py-12">
        <header class="space-y-1">
            <h1 class="text-3xl font-semibold text-stone-900">Posts</h1>
            <p class="text-sm text-stone-500">Notes from the team on engineering, design and how we work.</p>
        </header>

        <hr class="my-6 border-stone-200">

        <div
            role="search"
            class="flex flex-col gap-3 sm:flex-row"
        >
            <div class="relative flex-1">
                <label
                    for="post-search"
                    class="sr-only"
                >Search posts</label>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-stone-400"
                >
                    <path
                        fill-rule="evenodd"
                        d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z"
                        clip-rule="evenodd"
                    />
                </svg>
                <input
                    id="post-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search posts…"
                    autocomplete="off"
                    class="w-full rounded-md border border-stone-200 bg-white py-2 pl-9 pr-3 text-base text-stone-900 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                >
            </div>

            <div class="sm:w-52">
                <label
                    for="post-category"
                    class="sr-only"
                >Category</label>
                <select
                    id="post-category"
                    wire:model.live="category"
                    class="w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-base text-stone-800 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                >
                    <option value="">All categories</option>
                    @foreach (PostCategory::cases() as $categoryOption)
                        <option
                            wire:key="category-{{ $categoryOption->value }}"
                            value="{{ $categoryOption->value }}"
                        >{{ $categoryOption->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <p
            aria-live="polite"
            class="mt-4 text-sm text-stone-500"
        >{{ $this->summary }}</p>

        @if ($this->total === 0)
            <div class="space-y-3 py-12 text-center">
                @if ($search !== '' || $category !== '')
                    <p class="text-sm text-stone-500">No posts match your filters.</p>
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="cursor-pointer text-sm font-medium text-stone-700 underline underline-offset-2 hover:text-stone-900"
                    >Clear filters</button>
                @else
                    <p class="text-sm text-stone-500">No posts yet.</p>
                @endif
            </div>
        @endif

        {{-- Always rendered: an island can't sit inside a conditional. --}}
        <ol @class(['mt-2 divide-y divide-stone-200', 'hidden' => $this->total === 0])>
            @island(name: 'posts')
                @foreach ($this->posts as $post)
                    <li wire:key="post-{{ $post->id }}">
                        <article class="space-y-2 py-6">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-stone-500">
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $post->category->label() }}</span>
                                <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('M j, Y') }}</time>
                                <span aria-hidden="true">·</span>
                                <span>{{ $post->readingTime() }} min read</span>
                            </p>
                            <h2 class="text-lg font-semibold text-stone-900">
                                <a
                                    href="{{ route('posts.show', $post) }}"
                                    class="hover:underline hover:underline-offset-2"
                                >{{ $post->title }}</a>
                            </h2>
                            <p class="text-sm leading-6 text-stone-600">{{ $post->excerpt }}</p>
                            <p class="text-xs text-stone-400">By {{ $post->author_name }}</p>
                        </article>
                    </li>
                @endforeach
            @endisland
        </ol>

        @if ($this->total > 0)
            @if ($this->hasMore())
                <div
                    wire:key="sentinel-{{ $page }}"
                    wire:intersect.margin.300px="loadMore"
                    class="flex justify-center border-t border-stone-200 pt-6"
                >
                    <button
                        type="button"
                        wire:click="loadMore"
                        wire:loading.remove
                        wire:target="loadMore"
                        class="cursor-pointer rounded-md px-3 py-1.5 text-sm text-stone-600 ring-1 ring-stone-200 hover:bg-stone-50 hover:text-stone-900"
                    >Load more posts</button>
                    <p
                        wire:loading
                        wire:target="loadMore"
                        class="text-sm text-stone-500"
                    >Loading more posts…</p>
                </div>
            @else
                <p class="border-t border-stone-200 pt-6 text-center text-sm text-stone-400">You’ve reached the end.</p>
            @endif
        @endif
    </article>
</div>
