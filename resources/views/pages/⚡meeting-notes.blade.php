<?php

use App\Models\Meeting;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public Meeting $meeting;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('nullable|string')]
    public ?string $notes = null;

    public function mount(Meeting $meeting): void
    {
        $this->meeting = $meeting;
        $this->title = $meeting->title;
        $this->date = $meeting->date->toDateString();
        $this->notes = $meeting->notes;
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);

        $this->meeting->update([$property => $this->{$property}]);
    }
};
?>

<div class="min-h-screen bg-stone-100 px-4 py-10 sm:py-16">
    <article class="mx-auto max-w-3xl rounded-lg bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200 sm:px-12 sm:py-12">
        <header class="space-y-2">
            <label for="title" class="sr-only">Title</label>
            <input
                id="title"
                type="text"
                wire:model.live.blur="title"
                placeholder="Meeting title"
                class="w-full border-0 bg-transparent p-0 text-3xl font-semibold text-stone-900 placeholder:text-stone-300 focus:outline-none"
            >
            @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <label for="date" class="sr-only">Date</label>
            <input
                id="date"
                type="date"
                wire:model.live="date"
                class="border-0 bg-transparent p-0 text-sm text-stone-500 focus:outline-none"
            >
            @error('date') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </header>

        <hr class="my-6 border-stone-200">

        <section>
            <label for="notes" class="sr-only">Notes</label>
            <textarea
                id="notes"
                wire:model.live.debounce.750ms="notes"
                placeholder="Start taking notes…"
                rows="16"
                class="field-sizing-content min-h-96 w-full resize-none border-0 bg-transparent p-0 text-base leading-7 text-stone-800 placeholder:text-stone-300 focus:outline-none"
            ></textarea>
            @error('notes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        <footer class="mt-6 h-5 text-right text-xs text-stone-400">
            <span wire:loading.delay>Saving…</span>
            <span wire:loading.remove>Saved</span>
        </footer>
    </article>
</div>
