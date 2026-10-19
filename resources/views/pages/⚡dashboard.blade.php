<?php

use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public const LIST_LIMIT = 20;

    #[Locked]
    public User $user;

    public bool $onlyUnread = false;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function markAsRead(string $notificationId): void
    {
        $this->user->notifications()->findOrFail($notificationId)->markAsRead();
    }

    public function markAsUnread(string $notificationId): void
    {
        $this->user->notifications()->findOrFail($notificationId)->markAsUnread();
    }

    public function markAllAsRead(): void
    {
        $updated = $this->user->unreadNotifications()->update(['read_at' => now()]);

        if ($updated > 0) {
            $this->dispatch('toast', type: 'success', message: 'All notifications marked as read.');
        }
    }

    /**
     * Get the newest notifications, or only the unread ones when that switch is on.
     *
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        $query = $this->onlyUnread ? $this->user->unreadNotifications() : $this->user->notifications();

        return $query->limit(self::LIST_LIMIT)->get();
    }

    /**
     * Get the notifications grouped under Today, Yesterday and Older, newest group first.
     *
     * @return array<string, Collection<int, DatabaseNotification>>
     */
    #[Computed]
    public function notificationGroups(): array
    {
        return $this->notifications
            ->groupBy(fn (DatabaseNotification $notification): string => match (true) {
                $notification->created_at->isToday() => 'Today',
                $notification->created_at->isYesterday() => 'Yesterday',
                default => 'Older',
            })
            ->all();
    }

    /**
     * Get how many notifications there are in total, unread and read.
     *
     * @return array{all: int, unread: int, read: int}
     */
    #[Computed]
    public function counts(): array
    {
        $total = $this->user->notifications()->count();
        $unread = $this->user->unreadNotifications()->count();

        return [
            'all' => $total,
            'unread' => $unread,
            'read' => $total - $unread,
        ];
    }

    #[Computed]
    public function publishedPostCount(): int
    {
        return Post::query()->published()->count();
    }

    #[Computed]
    public function productCount(): int
    {
        return Product::query()->count();
    }
};
?>

<div
    wire:poll.10s
    x-data="{ isNotificationsOpen: false, isProfileMenuOpen: false }"
    class="min-h-screen bg-stone-100"
>
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
            <h1 class="text-xl font-semibold text-stone-900">Dashboard</h1>

            <div class="flex items-center gap-3">
                <div
                    x-on:click.outside="isNotificationsOpen = false"
                    x-on:keydown.escape="isNotificationsOpen = false"
                    class="sm:relative"
                >
                    <button
                        type="button"
                        x-on:click="isNotificationsOpen = ! isNotificationsOpen"
                        x-bind:aria-expanded="isNotificationsOpen"
                        aria-controls="notifications-panel"
                        aria-label="Notifications, {{ $this->counts['unread'] }} unread"
                        x-bind:class="isNotificationsOpen ? 'bg-sky-50 text-sky-700' : 'text-stone-500 hover:bg-stone-100 hover:text-stone-900'"
                        class="relative cursor-pointer rounded-full p-2"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                            class="size-5"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 2a6 6 0 0 0-6 6c0 1.887-.454 3.665-1.257 5.234a.75.75 0 0 0 .515 1.076 32.91 32.91 0 0 0 3.256.508 3.5 3.5 0 0 0 6.972 0 32.903 32.903 0 0 0 3.256-.508.75.75 0 0 0 .515-1.076A11.448 11.448 0 0 1 16 8a6 6 0 0 0-6-6ZM8.05 14.943a33.54 33.54 0 0 0 3.9 0 2 2 0 0 1-3.9 0Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        @if ($this->counts['unread'] > 0)
                            <span
                                aria-hidden="true"
                                class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-semibold text-white"
                            >{{ $this->counts['unread'] > 99 ? '99+' : $this->counts['unread'] }}</span>
                        @endif
                    </button>

                    <section
                        id="notifications-panel"
                        x-show="isNotificationsOpen"
                        x-cloak
                        x-transition.opacity.duration.150ms
                        aria-labelledby="notifications-heading"
                        class="fixed inset-x-4 top-16 z-30 flex max-h-[calc(100vh-5rem)] flex-col overflow-hidden rounded-md bg-white shadow-xl ring-1 ring-stone-200 sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:max-h-[70vh] sm:w-[28rem]"
                    >
                        <div class="flex items-center justify-between gap-4 px-5 pb-2 pt-4">
                            <h2
                                id="notifications-heading"
                                class="text-lg font-semibold text-stone-900"
                            >Notifications</h2>

                            <label class="flex cursor-pointer items-center gap-2 text-xs text-stone-500">
                                Only show unread
                                <button
                                    type="button"
                                    role="switch"
                                    wire:click="$toggle('onlyUnread')"
                                    aria-checked="{{ $onlyUnread ? 'true' : 'false' }}"
                                    @class([
                                        'relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors',
                                        'bg-sky-600' => $onlyUnread,
                                        'bg-stone-300' => ! $onlyUnread,
                                    ])
                                >
                                    <span @class([
                                        'inline-block size-4 rounded-full bg-white shadow transition-transform',
                                        'translate-x-4.5' => $onlyUnread,
                                        'translate-x-0.5' => ! $onlyUnread,
                                    ])></span>
                                </button>
                            </label>
                        </div>

                        <div class="flex items-center justify-between gap-4 border-b border-stone-200 px-5 pb-3 text-xs">
                            <p class="text-stone-400">{{ $this->counts['unread'] }} unread · {{ $this->counts['read'] }} read · Checks every 10 seconds</p>
                            <button
                                type="button"
                                wire:click="markAllAsRead"
                                @disabled($this->counts['unread'] === 0)
                                class="shrink-0 cursor-pointer whitespace-nowrap font-medium text-sky-700 hover:underline disabled:cursor-not-allowed disabled:text-stone-300 disabled:no-underline"
                            >Mark all as read</button>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            @forelse ($this->notificationGroups as $groupLabel => $groupNotifications)
                                <div wire:key="group-{{ Str::slug($groupLabel) }}">
                                    <h3 class="px-5 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-stone-400">{{ $groupLabel }}</h3>

                                    <ul>
                                        @foreach ($groupNotifications as $notification)
                                            <li
                                                wire:key="notification-{{ $notification->id }}"
                                                @class([
                                                    'group flex items-start gap-3 px-5 py-3 hover:bg-stone-50',
                                                    'bg-sky-50/60' => $notification->unread(),
                                                ])
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-stone-200 text-stone-600"
                                                >
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 20 20"
                                                        fill="currentColor"
                                                        class="size-4"
                                                    >
                                                        <path
                                                            fill-rule="evenodd"
                                                            d="M10 2a6 6 0 0 0-6 6c0 1.887-.454 3.665-1.257 5.234a.75.75 0 0 0 .515 1.076 32.91 32.91 0 0 0 3.256.508 3.5 3.5 0 0 0 6.972 0 32.903 32.903 0 0 0 3.256-.508.75.75 0 0 0 .515-1.076A11.448 11.448 0 0 1 16 8a6 6 0 0 0-6-6ZM8.05 14.943a33.54 33.54 0 0 0 3.9 0 2 2 0 0 1-3.9 0Z"
                                                            clip-rule="evenodd"
                                                        />
                                                    </svg>
                                                </span>

                                                <div class="min-w-0 flex-1 space-y-0.5">
                                                    <p @class([
                                                        'text-sm',
                                                        'font-semibold text-stone-900' => $notification->unread(),
                                                        'font-medium text-stone-600' => $notification->read(),
                                                    ])>
                                                        @if ($notification->unread())
                                                            <span class="sr-only">Unread:</span>
                                                        @endif
                                                        {{ $notification->data['title'] ?? 'Notification' }}
                                                    </p>
                                                    <p class="text-sm text-stone-500">{{ $notification->data['message'] ?? '' }}</p>
                                                    <time
                                                        datetime="{{ $notification->created_at->toIso8601String() }}"
                                                        title="{{ $notification->created_at->format('M j, Y g:i A') }}"
                                                        class="text-xs text-stone-400"
                                                    >{{ $notification->created_at->diffForHumans() }}</time>
                                                </div>

                                                @if ($notification->unread())
                                                    <button
                                                        type="button"
                                                        wire:click="markAsRead('{{ $notification->id }}')"
                                                        aria-label="Mark as read"
                                                        title="Mark as read"
                                                        class="mt-1 flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-full hover:bg-sky-100"
                                                    >
                                                        <span class="size-2.5 rounded-full bg-sky-600"></span>
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        wire:click="markAsUnread('{{ $notification->id }}')"
                                                        aria-label="Mark as unread"
                                                        title="Mark as unread"
                                                        class="mt-1 flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-full opacity-0 hover:bg-stone-200 focus-visible:opacity-100 group-hover:opacity-100"
                                                    >
                                                        <span class="size-2.5 rounded-full ring-2 ring-stone-400"></span>
                                                    </button>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @empty
                                <p class="px-5 py-12 text-center text-sm text-stone-500">{{ $onlyUnread ? 'You’re all caught up.' : 'No notifications yet.' }}</p>
                            @endforelse

                            @if ($this->counts[$onlyUnread ? 'unread' : 'all'] > self::LIST_LIMIT)
                                <p class="border-t border-stone-200 px-5 py-3 text-center text-xs text-stone-400">Showing the {{ self::LIST_LIMIT }} newest.</p>
                            @endif
                        </div>
                    </section>
                </div>

                <div
                    x-on:click.outside="isProfileMenuOpen = false"
                    x-on:keydown.escape="isProfileMenuOpen = false"
                    class="relative"
                >
                    <button
                        type="button"
                        x-on:click="isProfileMenuOpen = ! isProfileMenuOpen"
                        x-bind:aria-expanded="isProfileMenuOpen"
                        aria-haspopup="menu"
                        aria-label="Profile menu"
                        class="flex cursor-pointer items-center gap-2 rounded-full p-0.5 pr-2 hover:bg-stone-100"
                    >
                        <span class="flex size-8 items-center justify-center rounded-full bg-stone-800 text-xs font-semibold text-white">{{ $user->initials() }}</span>
                        <span class="hidden text-sm font-medium text-stone-700 sm:inline">{{ $user->name }}</span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                            class="size-4 text-stone-400"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>

                    <div
                        x-show="isProfileMenuOpen"
                        x-cloak
                        x-transition.opacity.duration.150ms
                        role="menu"
                        class="absolute right-0 z-30 mt-2 w-56 overflow-hidden rounded-md bg-white shadow-lg ring-1 ring-stone-200"
                    >
                        <div class="border-b border-stone-200 px-4 py-3">
                            <p class="truncate text-sm font-medium text-stone-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-stone-500">{{ $user->email }}</p>
                        </div>
                        <div class="py-1 text-sm">
                            <a
                                href="{{ route('posts.index') }}"
                                role="menuitem"
                                class="block px-4 py-2 text-stone-700 hover:bg-stone-50 hover:text-stone-900"
                            >Posts</a>
                            <a
                                href="{{ route('products.index') }}"
                                role="menuitem"
                                class="block px-4 py-2 text-stone-700 hover:bg-stone-50 hover:text-stone-900"
                            >Products</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <p class="text-sm text-stone-500">Welcome back, {{ $user->name }}.</p>

        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white px-5 py-4 shadow-sm ring-1 ring-stone-200">
                <dt class="text-sm text-stone-500">Unread notifications</dt>
                <dd class="mt-1 text-2xl font-semibold text-stone-900">{{ $this->counts['unread'] }}</dd>
            </div>
            <div class="rounded-lg bg-white px-5 py-4 shadow-sm ring-1 ring-stone-200">
                <dt class="text-sm text-stone-500">Published posts</dt>
                <dd class="mt-1 text-2xl font-semibold text-stone-900">{{ $this->publishedPostCount }}</dd>
            </div>
            <div class="rounded-lg bg-white px-5 py-4 shadow-sm ring-1 ring-stone-200">
                <dt class="text-sm text-stone-500">Products</dt>
                <dd class="mt-1 text-2xl font-semibold text-stone-900">{{ $this->productCount }}</dd>
            </div>
        </dl>
    </main>
</div>
