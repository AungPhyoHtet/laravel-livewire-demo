@props([
    'event' => 'toast',
    'duration' => 3000,
])

<div
    x-data="{ isVisible: false, message: '', hideTimer: null }"
    x-on:{{ $event }}.window="
        message = $event.detail.message;
        isVisible = true;
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => isVisible = false, {{ (int) $duration }});
    "
    x-show="isVisible"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="translate-y-2 opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-cloak
    role="status"
    aria-live="polite"
    {{ $attributes->class('fixed right-4 top-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-lg bg-white px-4 py-3 shadow-lg ring-1 ring-stone-200') }}
>
    <svg class="mt-0.5 size-5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
    </svg>

    <p class="flex-1 text-sm text-stone-800" x-text="message"></p>

    <button
        type="button"
        x-on:click="isVisible = false"
        class="text-stone-400 hover:text-stone-600"
        aria-label="Dismiss"
    >
        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
        </svg>
    </button>
</div>
