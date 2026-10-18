<?php

use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Component;

new class extends Component {
    #[Session]
    public bool $isOpen = false;

    #[Locked]
    #[Session]
    public int $step = 1;

    #[Session]
    public string $name = '';

    #[Session]
    public string $category = '';

    #[Session]
    public ?string $description = null;

    #[Session]
    public string $price = '';

    #[Session]
    public ?string $url = null;

    /**
     * The labels of the wizard steps, keyed by step number.
     *
     * @var array<int, string>
     */
    public const STEPS = [
        1 => 'Details',
        2 => 'Pricing & link',
        3 => 'Review',
    ];

    /**
     * Get the validation rules for the product.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(ProductCategory::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * Get the fields that are filled in on the given step.
     *
     * @return array<int, string>
     */
    protected function stepFields(int $step): array
    {
        return match ($step) {
            1 => ['name', 'category', 'description'],
            2 => ['price', 'url'],
            default => [],
        };
    }

    public function open(): void
    {
        $this->isOpen = true;
    }

    /**
     * Hide the wizard but keep the draft, e.g. after Escape or a backdrop click.
     */
    public function dismiss(): void
    {
        $this->isOpen = false;
    }

    /**
     * Close the wizard and discard the draft.
     */
    public function close(): void
    {
        $this->resetDraft();

        $this->isOpen = false;
    }

    public function submitStep(): void
    {
        if ($this->step < count(self::STEPS)) {
            $this->nextStep();

            return;
        }

        $this->create();
    }

    public function nextStep(): void
    {
        $this->validate(array_intersect_key($this->rules(), array_flip($this->stepFields($this->step))));

        $this->step = min($this->step + 1, count(self::STEPS));
    }

    public function previousStep(): void
    {
        $this->resetValidation();

        $this->step = max($this->step - 1, 1);
    }

    public function editStep(int $step): void
    {
        if ($step < 1 || $step >= $this->step) {
            return;
        }

        $this->resetValidation();

        $this->step = $step;
    }

    public function create(): void
    {
        $validated = $this->validate();

        Product::create($validated);

        $this->dispatch('product-created');
        $this->dispatch('toast', type: 'success', message: 'Product created.');

        $this->close();
    }

    /**
     * Get the price formatted for display, e.g. "$1,234.50".
     */
    public function formattedPrice(): string
    {
        return '$' . number_format((float) $this->price, 2);
    }

    protected function resetDraft(): void
    {
        $this->reset(['step', 'name', 'category', 'description', 'price', 'url']);
        $this->resetValidation();
    }
};
?>

<div>
    <button
        type="button"
        wire:click="open"
        class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2"
    >
        Create Product
    </button>

    <div
        wire:show="isOpen"
        x-transition.opacity.duration.200ms
        x-trap.noscroll="$wire.isOpen"
        x-on:keydown.escape.window="$wire.isOpen && $wire.dismiss()"
        x-cloak
        class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto px-4 py-10 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="create-product-title"
    >
        <div
            wire:click="dismiss"
            class="fixed inset-0 bg-stone-900/40"
            aria-hidden="true"
        ></div>

        <div class="relative w-full max-w-xl rounded-lg bg-white shadow-xl ring-1 ring-stone-200">
            <header class="border-b border-stone-200 px-6 pb-5 pt-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="create-product-title" class="text-xl font-semibold text-stone-900">Create product</h2>
                        <p class="mt-1 text-sm text-stone-500">Step {{ $step }} of {{ count($this::STEPS) }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="dismiss"
                        class="rounded p-1 text-stone-400 hover:text-stone-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300"
                        aria-label="Close"
                    >
                        <svg
                            class="size-5"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                            />
                        </svg>
                    </button>
                </div>

                <ol class="mt-5 flex items-center gap-2">
                    @foreach ($this::STEPS as $stepNumber => $stepLabel)
                        <li
                            wire:key="step-{{ $stepNumber }}"
                            class="flex flex-1 items-center gap-2"
                            @if ($stepNumber === $step) aria-current="step" @endif
                        >
                            <span @class([
                                'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                'bg-stone-900 text-white' => $stepNumber < $step,
                                'bg-white text-stone-900 ring-2 ring-stone-900' => $stepNumber === $step,
                                'bg-white text-stone-400 ring-1 ring-stone-300' => $stepNumber > $step,
                            ])>
                                @if ($stepNumber < $step)
                                    <svg
                                        class="size-4"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                @else
                                    {{ $stepNumber }}
                                @endif
                            </span>
                            <span @class([
                                'hidden text-sm sm:block',
                                'font-medium text-stone-900' => $stepNumber <= $step,
                                'text-stone-400' => $stepNumber > $step,
                            ])>{{ $stepLabel }}</span>
                            @if (!$loop->last)
                                <span class="h-px flex-1 bg-stone-200" aria-hidden="true"></span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </header>

            <form wire:submit="submitStep">
                <div class="space-y-5 px-6 py-6">
                    @if ($step === 1)
                        <div class="space-y-1.5">
                            <label for="product-name" class="block text-sm font-medium text-stone-700">Name</label>
                            <input
                                id="product-name"
                                type="text"
                                wire:model.live.debounce.500ms="name"
                                placeholder="Noise-cancelling headphones"
                                class="w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-base text-stone-900 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                            >
                            @error('name')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="product-category" class="block text-sm font-medium text-stone-700">Category</label>
                            <select
                                id="product-category"
                                wire:model.live="category"
                                class="w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-base text-stone-800 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                            >
                                <option value="">Choose a category…</option>
                                @foreach (ProductCategory::cases() as $categoryOption)
                                    <option wire:key="category-{{ $categoryOption->value }}" value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>
                                @endforeach
                            </select>
                            @error('category')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="product-description" class="block text-sm font-medium text-stone-700">
                                Description <span class="font-normal text-stone-400">(optional)</span>
                            </label>
                            <textarea
                                id="product-description"
                                wire:model.live.debounce.500ms="description"
                                rows="4"
                                placeholder="What makes this product great?"
                                class="field-sizing-content min-h-28 w-full resize-none rounded-md border border-stone-200 bg-white px-3 py-2 text-base leading-7 text-stone-800 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                            ></textarea>
                            @error('description')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @elseif ($step === 2)
                        <div class="space-y-1.5">
                            <label for="product-price" class="block text-sm font-medium text-stone-700">Price</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">$</span>
                                <input
                                    id="product-price"
                                    type="text"
                                    inputmode="decimal"
                                    wire:model.live.debounce.500ms="price"
                                    placeholder="0.00"
                                    class="w-full rounded-md border border-stone-200 bg-white py-2 pl-7 pr-3 text-base text-stone-900 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                                >
                            </div>
                            @error('price')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="product-url" class="block text-sm font-medium text-stone-700">
                                URL <span class="font-normal text-stone-400">(optional)</span>
                            </label>
                            <input
                                id="product-url"
                                type="url"
                                wire:model.live.debounce.500ms="url"
                                placeholder="https://example.com/product"
                                class="w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-base text-stone-900 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                            >
                            @error('url')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <section class="rounded-md ring-1 ring-stone-200">
                            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-2.5">
                                <h3 class="text-sm font-semibold text-stone-900">Details</h3>
                                <button
                                    type="button"
                                    wire:click="editStep(1)"
                                    class="text-sm font-medium text-stone-600 underline-offset-2 hover:text-stone-900 hover:underline"
                                >Edit<span class="sr-only"> details</span></button>
                            </div>
                            <dl class="divide-y divide-stone-100 text-sm">
                                <div class="grid grid-cols-3 gap-4 px-4 py-2.5">
                                    <dt class="text-stone-500">Name</dt>
                                    <dd class="col-span-2 text-stone-900">{{ $name }}</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4 px-4 py-2.5">
                                    <dt class="text-stone-500">Category</dt>
                                    <dd class="col-span-2 text-stone-900">{{ ProductCategory::tryFrom($category)?->label() ?? '—' }}</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4 px-4 py-2.5">
                                    <dt class="text-stone-500">Description</dt>
                                    <dd class="col-span-2 whitespace-pre-line text-stone-900">{{ filled($description) ? $description : '—' }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="rounded-md ring-1 ring-stone-200">
                            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-2.5">
                                <h3 class="text-sm font-semibold text-stone-900">Pricing &amp; link</h3>
                                <button
                                    type="button"
                                    wire:click="editStep(2)"
                                    class="text-sm font-medium text-stone-600 underline-offset-2 hover:text-stone-900 hover:underline"
                                >Edit<span class="sr-only"> pricing and link</span></button>
                            </div>
                            <dl class="divide-y divide-stone-100 text-sm">
                                <div class="grid grid-cols-3 gap-4 px-4 py-2.5">
                                    <dt class="text-stone-500">Price</dt>
                                    <dd class="col-span-2 text-stone-900">{{ $this->formattedPrice() }}</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4 px-4 py-2.5">
                                    <dt class="text-stone-500">URL</dt>
                                    <dd class="col-span-2 break-all text-stone-900">{{ filled($url) ? $url : '—' }}</dd>
                                </div>
                            </dl>
                        </section>
                    @endif
                </div>

                <footer class="flex items-center justify-between gap-3 border-t border-stone-200 px-6 py-4">
                    @if ($step === 1)
                        <button
                            type="button"
                            wire:click="close"
                            class="rounded-md px-4 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100 hover:text-stone-900"
                        >Cancel</button>
                    @else
                        <button
                            type="button"
                            wire:click="previousStep"
                            class="rounded-md px-4 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100 hover:text-stone-900"
                        >Back</button>
                    @endif

                    <button type="submit"
                        class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2 disabled:opacity-60"
                    >
                        @if ($step < count($this::STEPS))
                            Next
                        @else
                            <span wire:loading.remove wire:target="submitStep">Create product</span>
                            <span wire:loading wire:target="submitStep">Creating…</span>
                        @endif
                    </button>
                </footer>
            </form>
        </div>
    </div>
</div>
