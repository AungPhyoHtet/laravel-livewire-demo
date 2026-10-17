@props([
    'event' => 'toast',
    'duration' => 3000,
])

<div
    x-data="{
        toasts: [],
        duration: {{ (int) $duration }},
        add(detail) {
            const id = Date.now() + Math.random();

            this.toasts.push({ id, type: detail.type ?? 'success', message: detail.message, isVisible: false });
            this.$nextTick(() => this.toasts.find((toast) => toast.id === id).isVisible = true);
            setTimeout(() => this.dismiss(id), this.duration);
        },
        dismiss(id) {
            const toast = this.toasts.find((toast) => toast.id === id);

            if (! toast) {
                return;
            }

            toast.isVisible = false;
            setTimeout(() => this.toasts = this.toasts.filter((toast) => toast.id !== id), 300);
        },
    }"
    x-on:{{ $event }}.window="add($event.detail)"
    x-cloak
    role="status"
    aria-live="polite"
    {{ $attributes->class('pointer-events-none fixed right-4 top-4 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2') }}
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.isVisible"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex items-start gap-3 rounded-lg px-4 py-3 shadow-lg ring-1"
            :class="{
                'bg-emerald-50 text-emerald-900 ring-emerald-200': toast.type === 'success',
                'bg-sky-50 text-sky-900 ring-sky-200': toast.type === 'info',
                'bg-amber-50 text-amber-900 ring-amber-200': toast.type === 'warning',
                'bg-red-50 text-red-900 ring-red-200': toast.type === 'error',
            }"
        >
            <svg x-show="toast.type === 'success'" class="mt-0.5 size-5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
            </svg>
            <svg x-show="toast.type === 'info'" class="mt-0.5 size-5 shrink-0 text-sky-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
            </svg>
            <svg x-show="toast.type === 'warning'" class="mt-0.5 size-5 shrink-0 text-amber-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>
            <svg x-show="toast.type === 'error'" class="mt-0.5 size-5 shrink-0 text-red-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" />
            </svg>

            <p class="flex-1 text-sm" x-text="toast.message"></p>

            <button
                type="button"
                x-on:click="dismiss(toast.id)"
                class="opacity-50 hover:opacity-100"
                aria-label="Dismiss"
            >
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        </div>
    </template>
</div>
