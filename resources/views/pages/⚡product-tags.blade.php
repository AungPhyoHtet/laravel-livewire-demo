<?php

use App\Models\Product;
use App\Models\Tag;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public const MAX_TAGS = 10;

    public const MAX_SUGGESTIONS = 6;

    #[Locked]
    public Product $product;

    public string $query = '';

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    /**
     * Get the product's tags, in the order they were added.
     *
     * @return Collection<int, Tag>
     */
    #[Computed]
    public function tags(): Collection
    {
        return $this->product->tags()->get();
    }

    /**
     * Get existing tags that contain the typed text and aren't on the product yet, prefix matches first.
     *
     * @return Collection<int, Tag>
     */
    #[Computed]
    public function suggestions(): Collection
    {
        $search = $this->normalise($this->query);

        if ($search === '') {
            return new Collection;
        }

        $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);

        return Tag::query()
            ->whereRaw("name like ? escape '!'", ['%'.$escapedSearch.'%'])
            ->whereKeyNot($this->tags->modelKeys())
            ->orderByRaw("case when name like ? escape '!' then 0 else 1 end", [$escapedSearch.'%'])
            ->orderBy('name')
            ->limit(self::MAX_SUGGESTIONS)
            ->get();
    }

    /**
     * Determine whether the typed text is a valid name for a brand new tag.
     */
    #[Computed]
    public function isNewTag(): bool
    {
        $search = $this->normalise($this->query);

        return $search !== ''
            && $this->tagValidator($search)->passes()
            && ! Tag::where('name', $search)->exists();
    }

    public function addTag(string $name): void
    {
        $name = $this->normalise($name);

        if ($name === '') {
            return;
        }

        $validator = $this->tagValidator($name);

        if ($validator->fails()) {
            $this->rejectTag($validator->errors()->first('tag'));

            return;
        }

        if ($this->tags->contains('name', $name)) {
            $this->query = '';
            $this->resetErrorBag('query');
            $this->dispatch('toast', type: 'warning', message: "“{$name}” is already added.");

            return;
        }

        if ($this->tags->count() >= self::MAX_TAGS) {
            $this->rejectTag('A product can have up to '.self::MAX_TAGS.' tags.');

            return;
        }

        $this->product->tags()->attach(Tag::firstOrCreate(['name' => $name]));
        unset($this->tags);

        $this->query = '';
        $this->resetErrorBag('query');
        $this->dispatch('toast', type: 'success', message: "Added “{$name}”.");
    }

    public function removeTag(int $tagId): void
    {
        $tag = $this->tags->find($tagId);

        if ($tag === null) {
            return;
        }

        $this->product->tags()->detach($tag);
        unset($this->tags);

        $this->dispatch('toast', type: 'info', message: "Removed “{$tag->name}”.");
    }

    /**
     * Split a suggestion around the typed text so the match can be emphasised.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function splitOnMatch(string $name): array
    {
        $search = $this->normalise($this->query);
        $position = $search === '' ? false : mb_strpos($name, $search);

        if ($position === false) {
            return [$name, '', ''];
        }

        $length = mb_strlen($search);

        return [
            mb_substr($name, 0, $position),
            mb_substr($name, $position, $length),
            mb_substr($name, $position + $length),
        ];
    }

    protected function normalise(string $name): string
    {
        return Str::lower(Str::squish($name));
    }

    protected function tagValidator(string $name): ValidatorContract
    {
        return Validator::make(
            ['tag' => $name],
            ['tag' => ['string', 'max:30', 'not_regex:/,/']],
            [
                'tag.max' => 'Tags can be at most :max characters.',
                'tag.not_regex' => 'Tags can’t contain commas.',
            ],
        );
    }

    protected function rejectTag(string $message): void
    {
        $this->addError('query', $message);
        $this->dispatch('toast', type: 'error', message: $message);
    }
};
?>

<div class="min-h-screen bg-stone-100 px-4 py-10 sm:py-16">
    <article class="mx-auto max-w-3xl rounded-lg bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200 sm:px-12 sm:py-12">
        <header class="space-y-1">
            <a
                href="{{ route('products.index') }}"
                class="text-sm text-stone-500 hover:text-stone-900"
            >&larr; Products</a>
            <h1 class="text-3xl font-semibold text-stone-900">{{ $product->name }}</h1>
            <p class="text-sm text-stone-500">Tag this product so it’s easier to find. Changes are saved automatically.</p>
        </header>

        <hr class="my-6 border-stone-200">

        @php($hasDropdown = $this->suggestions->isNotEmpty() || $this->isNewTag)

        <div
            x-data="{
                open: false,
                highlighted: -1,
                options() {
                    return [...this.$root.querySelectorAll('[role=option]')];
                },
                move(step) {
                    const count = this.options().length;

                    if (count === 0) {
                        return;
                    }

                    this.open = true;
                    this.highlighted = this.highlighted < 0
                        ? (step > 0 ? 0 : count - 1)
                        : (this.highlighted + step + count) % count;
                    this.options()[this.highlighted].scrollIntoView({ block: 'nearest' });
                },
                commit() {
                    const option = this.options()[this.highlighted];
                    const name = option ? option.dataset.name : this.$wire.query;

                    this.highlighted = -1;

                    if (name.trim() !== '') {
                        this.$wire.addTag(name);
                    }
                },
                removeLast(event) {
                    const chips = this.$root.querySelectorAll('[data-tag-id]');

                    if (event.target.value !== '' || chips.length === 0) {
                        return;
                    }

                    event.preventDefault();
                    this.$wire.removeTag(Number(chips[chips.length - 1].dataset.tagId));
                },
            }"
            class="space-y-1.5"
        >
            <div class="flex items-baseline justify-between">
                <label
                    for="tag-input"
                    class="block text-sm font-medium text-stone-700"
                >Tags</label>
                <span class="text-xs text-stone-400">{{ $this->tags->count() }} / {{ $this::MAX_TAGS }} tags</span>
            </div>

            <div class="relative">
                <div
                    x-on:click="$refs.input.focus()"
                    class="flex min-h-11 cursor-text flex-wrap items-center gap-1.5 rounded-md border border-stone-200 bg-white px-2 py-1.5 focus-within:border-stone-400 focus-within:ring-2 focus-within:ring-stone-200"
                >
                    @foreach ($this->tags as $tag)
                        <span
                            wire:key="tag-{{ $tag->id }}"
                            data-tag-id="{{ $tag->id }}"
                            class="inline-flex items-center gap-1 rounded-full bg-stone-100 py-0.5 pl-2.5 pr-1 text-sm text-stone-700"
                        >
                            {{ $tag->name }}
                            <button
                                type="button"
                                wire:click="removeTag({{ $tag->id }})"
                                aria-label="Remove {{ $tag->name }}"
                                class="rounded-full p-0.5 text-stone-400 hover:bg-stone-200 hover:text-stone-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 16 16"
                                    fill="currentColor"
                                    aria-hidden="true"
                                    class="size-3.5"
                                >
                                    <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                </svg>
                            </button>
                        </span>
                    @endforeach

                    <input
                        id="tag-input"
                        x-ref="input"
                        type="text"
                        role="combobox"
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="tag-suggestions"
                        x-bind:aria-expanded="open && {{ $hasDropdown ? 'true' : 'false' }} ? 'true' : 'false'"
                        x-bind:aria-activedescendant="highlighted >= 0 ? 'tag-option-' + highlighted : null"
                        wire:model.live.debounce.250ms="query"
                        x-on:input="highlighted = -1; open = true"
                        x-on:focus="open = true"
                        x-on:blur="open = false; highlighted = -1"
                        x-on:keydown.down.prevent="move(1)"
                        x-on:keydown.up.prevent="move(-1)"
                        x-on:keydown.escape="open = false; highlighted = -1"
                        x-on:keydown.enter.prevent="commit()"
                        x-on:keydown.comma.prevent="commit()"
                        x-on:keydown.backspace="removeLast($event)"
                        placeholder="Add a tag…"
                        class="min-w-32 flex-1 border-0 bg-transparent px-1 py-1 text-base text-stone-900 placeholder:text-stone-300 focus:outline-none focus:ring-0"
                    >
                </div>

                @if ($hasDropdown)
                    <div
                        x-show="open"
                        x-cloak
                        class="absolute z-10 mt-1 w-full overflow-hidden rounded-md bg-white shadow-lg ring-1 ring-stone-200"
                    >
                        <ul
                            id="tag-suggestions"
                            role="listbox"
                            aria-label="Tag suggestions"
                            @class(['max-h-60 overflow-y-auto', 'py-1' => $this->suggestions->isNotEmpty()])
                        >
                            @foreach ($this->suggestions as $index => $suggestion)
                                @php([$before, $match, $after] = $this->splitOnMatch($suggestion->name))
                                <li
                                    id="tag-option-{{ $index }}"
                                    wire:key="suggestion-{{ $suggestion->id }}"
                                    role="option"
                                    data-name="{{ $suggestion->name }}"
                                    x-bind:aria-selected="highlighted === {{ $index }} ? 'true' : 'false'"
                                    x-bind:class="highlighted === {{ $index }} ? 'bg-stone-100 text-stone-900' : 'text-stone-600'"
                                    x-on:mousedown.prevent
                                    x-on:mouseenter="highlighted = {{ $index }}"
                                    x-on:click="$wire.addTag($el.dataset.name)"
                                    class="cursor-pointer px-3 py-2 text-sm"
                                >{{ $before }}<span class="font-semibold text-stone-900">{{ $match }}</span>{{ $after }}</li>
                            @endforeach
                        </ul>

                        @if ($this->isNewTag)
                            <p @class(['px-3 py-2 text-xs text-stone-500', 'border-t border-stone-100' => $this->suggestions->isNotEmpty()])>Press Enter to add “{{ Str::lower(Str::squish($query)) }}”</p>
                        @endif
                    </div>
                @endif
            </div>

            @error('query') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="text-xs text-stone-400">Press Enter or comma to add a tag, Backspace to remove the last one.</p>
        </div>
    </article>
</div>
