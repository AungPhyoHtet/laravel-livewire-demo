<?php

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const RESULT_LIMIT = 8;

    public const SNIPPET_RADIUS = 60;

    public string $query = '';

    /**
     * Get the trimmed search terms.
     */
    protected function terms(): string
    {
        return Str::squish($this->query);
    }

    /**
     * Get the published posts whose title, excerpt or body contain the terms.
     *
     * @return Builder<Post>
     */
    protected function matchingQuery(): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->terms()).'%';

        return Post::query()
            ->published()
            ->where(fn (Builder $query) => $query
                ->whereRaw("title like ? escape '!'", [$pattern])
                ->orWhereRaw("excerpt like ? escape '!'", [$pattern])
                ->orWhereRaw("body like ? escape '!'", [$pattern]))
            ->orderByRaw("case when title like ? escape '!' then 0 else 1 end", [$pattern]);
    }

    /**
     * Get the matching posts in display order: grouped under their category, title matches first.
     *
     * Each result's index is its position in that order, which the arrow keys move through.
     *
     * @return array<int, array{index: int, category: string, url: string, title: HtmlString, snippet: HtmlString}>
     */
    #[Computed]
    public function results(): array
    {
        if ($this->terms() === '') {
            return [];
        }

        return $this->matchingQuery()
            ->latest('published_at')
            ->latest('id')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->groupBy(fn (Post $post): string => $post->category->label())
            ->flatten()
            ->map(fn (Post $post, int $index): array => [
                'index' => $index,
                'category' => $post->category->label(),
                'url' => route('posts.show', $post),
                'title' => $this->highlight($post->title),
                'snippet' => $this->highlight($this->snippet($post)),
            ])
            ->all();
    }

    /**
     * Get the results grouped under their category.
     *
     * @return Collection<string, Collection<int, array{index: int, category: string, url: string, title: HtmlString, snippet: HtmlString}>>
     */
    #[Computed]
    public function resultGroups(): Collection
    {
        return collect($this->results)->groupBy('category');
    }

    #[Computed]
    public function total(): int
    {
        return $this->terms() === '' ? 0 : $this->matchingQuery()->count();
    }

    /**
     * Get the text shown under the title: the excerpt, or the part of the body that matched when only the body did.
     */
    protected function snippet(Post $post): string
    {
        $terms = $this->terms();

        if ($terms === '' || Str::contains($post->title.' '.$post->excerpt, $terms, ignoreCase: true)) {
            return $post->excerpt;
        }

        return Str::excerpt(Str::squish($post->body), $terms, ['radius' => self::SNIPPET_RADIUS, 'omission' => '…']) ?? $post->excerpt;
    }

    /**
     * Escape the text and wrap every match of the terms in a mark element.
     */
    protected function highlight(string $text): HtmlString
    {
        $terms = $this->terms();

        if ($terms === '') {
            return new HtmlString(e($text));
        }

        $parts = preg_split('/('.preg_quote($terms, '/').')/iu', $text, flags: PREG_SPLIT_DELIM_CAPTURE);

        return new HtmlString(collect($parts)
            ->map(fn (string $part, int $index): string => $index % 2 === 1
                ? '<mark class="rounded-sm bg-amber-100 text-inherit">'.e($part).'</mark>'
                : e($part))
            ->implode(''));
    }
};
?>

<div
    x-data="{
        isOpen: false,
        activeIndex: 0,
        isMac: /Mac|iPhone|iPad/.test(navigator.userAgent),
        open() {
            this.isOpen = true;
            this.activeIndex = 0;
        },
        close() {
            this.isOpen = false;
        },
        options() {
            return Array.from(this.$root.querySelectorAll('[role=option]'));
        },
        move(step) {
            const count = this.options().length;

            if (count === 0) {
                return;
            }

            this.activeIndex = (this.activeIndex + step + count) % count;
            this.$nextTick(() => this.options()[this.activeIndex]?.scrollIntoView({ block: 'nearest' }));
        },
        choose() {
            this.options()[Math.min(this.activeIndex, this.options().length - 1)]?.click();
        },
        handleShortcut(event) {
            const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable;

            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                this.isOpen ? this.close() : this.open();
            } else if (event.key === '/' && ! isTyping && ! this.isOpen) {
                event.preventDefault();
                this.open();
            }
        },
    }"
    x-on:keydown.window="handleShortcut($event)"
>
    <button
        type="button"
        x-on:click="open()"
        aria-haspopup="dialog"
        class="flex w-full cursor-pointer items-center gap-2 rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-left text-sm text-stone-400 hover:border-stone-300 hover:text-stone-500 sm:w-64"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
            class="size-4 shrink-0"
        >
            <path
                fill-rule="evenodd"
                d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z"
                clip-rule="evenodd"
            />
        </svg>
        <span class="flex-1">Quick search…</span>
        <kbd
            class="hidden rounded border border-stone-200 bg-white px-1.5 font-sans text-xs text-stone-500 sm:inline"
            x-text="isMac ? '⌘K' : 'Ctrl K'"
        >Ctrl K</kbd>
    </button>

    <div
        x-show="isOpen"
        x-cloak
        x-trap.noscroll="isOpen"
        x-on:keydown.escape.prevent.stop="close()"
        class="fixed inset-0 z-50 flex items-start justify-center bg-stone-900/40 px-4 pt-[10vh] sm:pt-[15vh]"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Search posts"
            x-on:click.outside="close()"
            x-transition.opacity.duration.100ms
            class="flex max-h-[70vh] w-full max-w-xl flex-col overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-stone-200"
        >
            <div class="flex items-center gap-3 border-b border-stone-200 px-4">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                    class="size-5 shrink-0 text-stone-400"
                    wire:loading.remove
                    wire:target="query"
                >
                    <path
                        fill-rule="evenodd"
                        d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z"
                        clip-rule="evenodd"
                    />
                </svg>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    class="size-5 shrink-0 animate-spin text-stone-400"
                    wire:loading
                    wire:target="query"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                        stroke="currentColor"
                        stroke-opacity="0.25"
                        stroke-width="3"
                    />
                    <path
                        d="M21 12a9 9 0 0 0-9-9"
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-width="3"
                    />
                </svg>
                <input
                    type="text"
                    role="combobox"
                    aria-label="Search posts"
                    aria-autocomplete="list"
                    aria-controls="post-search-results"
                    aria-expanded="true"
                    x-bind:aria-activedescendant="'post-search-option-' + activeIndex"
                    wire:model.live.debounce.200ms="query"
                    x-on:input="activeIndex = 0"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="choose()"
                    placeholder="Search titles, excerpts and post text…"
                    autocomplete="off"
                    spellcheck="false"
                    class="w-full bg-transparent py-4 text-base text-stone-900 placeholder:text-stone-400 focus:outline-none"
                >
                <kbd class="hidden shrink-0 rounded border border-stone-200 px-1.5 text-xs text-stone-400 sm:inline">Esc</kbd>
            </div>

            <div class="flex-1 overflow-y-auto px-2 py-2">
                @if (Str::squish($query) === '')
                    <p class="px-3 py-10 text-center text-sm text-stone-500">Type to search post titles, excerpts and text.</p>
                @elseif ($this->results === [])
                    <p class="px-3 py-10 text-center text-sm text-stone-500">No posts match “{{ Str::squish($query) }}”.</p>
                @else
                    <p class="px-3 pb-1 pt-2 text-xs text-stone-400">{{ $this->total }} {{ Str::plural('result', $this->total) }}</p>

                    <div
                        id="post-search-results"
                        role="listbox"
                        aria-label="Posts"
                    >
                        @foreach ($this->resultGroups as $categoryLabel => $results)
                            <div
                                wire:key="group-{{ $categoryLabel }}"
                                role="group"
                                aria-labelledby="post-search-group-{{ Str::slug($categoryLabel) }}"
                            >
                                <h3
                                    id="post-search-group-{{ Str::slug($categoryLabel) }}"
                                    class="px-3 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-stone-500"
                                >{{ $categoryLabel }}</h3>

                                @foreach ($results as $result)
                                    <a
                                        wire:key="result-{{ $result['url'] }}"
                                        id="post-search-option-{{ $result['index'] }}"
                                        href="{{ $result['url'] }}"
                                        role="option"
                                        tabindex="-1"
                                        x-bind:aria-selected="activeIndex === {{ $result['index'] }}"
                                        x-on:mousemove="activeIndex = {{ $result['index'] }}"
                                        class="group flex items-start gap-3 rounded-md px-3 py-2.5 aria-selected:bg-stone-100"
                                    >
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-stone-900">{{ $result['title'] }}</span>
                                            <span class="mt-0.5 line-clamp-2 block text-xs leading-5 text-stone-500">{{ $result['snippet'] }}</span>
                                        </span>
                                        <span
                                            aria-hidden="true"
                                            class="invisible mt-0.5 shrink-0 text-xs text-stone-400 group-aria-selected:visible"
                                        >↵</span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach

                        @if ($this->total > count($this->results))
                            @php($seeAllIndex = count($this->results))
                            <a
                                wire:key="see-all"
                                id="post-search-option-{{ $seeAllIndex }}"
                                href="{{ route('posts.index', ['q' => Str::squish($query)]) }}"
                                role="option"
                                tabindex="-1"
                                x-bind:aria-selected="activeIndex === {{ $seeAllIndex }}"
                                x-on:mousemove="activeIndex = {{ $seeAllIndex }}"
                                class="mt-1 block rounded-md px-3 py-2.5 text-sm text-stone-600 aria-selected:bg-stone-100 aria-selected:text-stone-900"
                            >See all {{ $this->total }} results in Posts &rarr;</a>
                        @endif
                    </div>
                @endif
            </div>

            <footer class="hidden items-center gap-4 border-t border-stone-200 bg-stone-50 px-4 py-2 text-xs text-stone-500 sm:flex">
                <span><kbd class="rounded border border-stone-200 bg-white px-1">↵</kbd> to open</span>
                <span><kbd class="rounded border border-stone-200 bg-white px-1">↑</kbd> <kbd class="rounded border border-stone-200 bg-white px-1">↓</kbd> to navigate</span>
                <span><kbd class="rounded border border-stone-200 bg-white px-1">Esc</kbd> to close</span>
            </footer>
        </div>
    </div>
</div>
