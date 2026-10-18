<?php

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Get the products, newest first.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::latest()->latest('id')->get();
    }
};
?>

<div class="min-h-screen bg-stone-100 px-4 py-10 sm:py-16">
    <article class="mx-auto max-w-3xl rounded-lg bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200 sm:px-12 sm:py-12">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-3xl font-semibold text-stone-900">Products</h1>
                <p class="text-sm text-stone-500">Everything in your catalogue.</p>
            </div>

            <livewire:create-product-wizard @product-created="$refresh" />
        </header>

        <hr class="my-6 border-stone-200">

        @if ($this->products->isEmpty())
            <p class="py-10 text-center text-sm text-stone-500">No products yet. Create your first one.</p>
        @else
            <ul class="divide-y divide-stone-200">
                @foreach ($this->products as $product)
                    <li wire:key="product-{{ $product->id }}" class="flex flex-col gap-2 py-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-medium text-stone-900">{{ $product->name }}</h2>
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ $product->category->label() }}</span>
                            </div>

                            @if ($product->description)
                                <p class="text-sm text-stone-500">{{ $product->description }}</p>
                            @endif

                            @if ($product->url)
                                <a href="{{ $product->url }}" target="_blank" rel="noopener" class="block truncate text-sm text-stone-600 underline underline-offset-2 hover:text-stone-900">{{ $product->url }}</a>
                            @endif
                        </div>

                        <p class="shrink-0 font-medium text-stone-900">{{ $product->formattedPrice() }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>
</div>
