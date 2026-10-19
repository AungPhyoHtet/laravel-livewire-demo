<?php

use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Database\Seeders\NotificationSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Store a database notification for the user, created the given number of minutes ago.
 */
function notifyUser(User $user, string $title, int $minutesAgo = 1, bool $isRead = false): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => ActivityNotification::class,
        'data' => ['title' => $title, 'message' => "{$title} message."],
        'read_at' => $isRead ? now() : null,
        'created_at' => now()->subMinutes($minutesAgo),
    ]);
}

test('the dashboard lists the user’s notifications newest first and polls every 10 seconds', function () {
    $user = User::factory()->create(['name' => 'Alex Rivera']);
    notifyUser($user, 'Older notification', minutesAgo: 30, isRead: true);
    notifyUser($user, 'Newer notification', minutesAgo: 5);
    notifyUser(User::factory()->create(), 'Someone else’s notification');

    $this->get(route('users.dashboard', $user))
        ->assertOk()
        ->assertSee('Welcome back, Alex Rivera.')
        ->assertSeeInOrder(['Newer notification', 'Older notification'])
        ->assertSee('Newer notification message.')
        ->assertSee('5 minutes ago')
        ->assertSee('Unread:')
        ->assertDontSee('Someone else’s notification')
        ->assertSee('wire:poll.10s', false)
        ->assertSee('Checks every 10 seconds');
});

test('the header shows the title, the notifications button and the profile menu', function () {
    $user = User::factory()->create(['name' => 'Alex Rivera', 'email' => 'alex@example.com']);

    $this->get(route('users.dashboard', $user))
        ->assertOk()
        ->assertSeeInOrder(['<h1', 'Dashboard', 'aria-controls="notifications-panel"', 'aria-label="Profile menu"'], false)
        ->assertSeeInOrder(['AR', 'Alex Rivera', 'alex@example.com'])
        ->assertSee(route('posts.index'))
        ->assertSee(route('products.index'));
});

test('the notifications open in a dropdown panel under the bell, not a modal', function () {
    $user = User::factory()->create();

    $this->get(route('users.dashboard', $user))
        ->assertSee('x-on:click="isNotificationsOpen = ! isNotificationsOpen"', false)
        ->assertSee('x-on:click.outside="isNotificationsOpen = false"', false)
        ->assertSeeInOrder(['id="notifications-panel"', 'x-show="isNotificationsOpen"', 'aria-labelledby="notifications-heading"'], false)
        ->assertDontSee('aria-modal', false);
});

test('the dashboard shows unread, published post and product totals', function () {
    $user = User::factory()->create();
    notifyUser($user, 'Unread');
    Post::factory()->count(2)->create();
    Post::factory()->draft()->create();
    Product::factory()->count(3)->create();

    $page = Livewire::test('pages::dashboard', ['user' => $user]);

    expect($page->get('publishedPostCount'))->toBe(2)
        ->and($page->get('productCount'))->toBe(3);

    $page->assertSeeInOrder(['Unread notifications', '1', 'Published posts', '2', 'Products', '3']);
});

test('a missing user returns 404', function () {
    $this->get(route('users.dashboard', 999))->assertNotFound();
});

test('the dashboard counts all, unread and read notifications', function () {
    $user = User::factory()->create();
    notifyUser($user, 'First unread');
    notifyUser($user, 'Second unread');
    notifyUser($user, 'Read one', isRead: true);

    $page = Livewire::test('pages::dashboard', ['user' => $user]);

    expect($page->get('counts'))->toBe(['all' => 3, 'unread' => 2, 'read' => 1]);

    $page->assertSeeHtml('aria-label="Notifications, 2 unread"');
});

test('the unread badge caps at 99+', function () {
    $user = User::factory()->create();

    foreach (range(1, 100) as $number) {
        notifyUser($user, "Notification {$number}");
    }

    Livewire::test('pages::dashboard', ['user' => $user])
        ->assertSee('99+')
        ->assertSee('Showing the 20 newest.');
});

test('only show unread hides read notifications', function () {
    $user = User::factory()->create();
    notifyUser($user, 'Unread notification');
    notifyUser($user, 'Read notification', isRead: true);

    $page = Livewire::test('pages::dashboard', ['user' => $user])
        ->assertSeeHtml('role="switch"')
        ->assertSeeHtml('aria-checked="false"')
        ->toggle('onlyUnread')
        ->assertSeeHtml('aria-checked="true"');

    expect($page->get('notifications')->pluck('data.title')->all())->toBe(['Unread notification']);

    $page->toggle('onlyUnread');

    expect($page->get('notifications'))->toHaveCount(2);
});

test('the notifications are grouped by today, yesterday and older', function () {
    $this->travelTo(now()->setTime(15, 0));
    $user = User::factory()->create();
    notifyUser($user, 'Old notification', minutesAgo: 60 * 24 * 5);
    notifyUser($user, 'Yesterday’s notification', minutesAgo: 60 * 24);
    notifyUser($user, 'Today’s notification', minutesAgo: 10);

    $page = Livewire::test('pages::dashboard', ['user' => $user]);

    expect(collect($page->get('notificationGroups'))->map(fn ($group) => $group->pluck('data.title')->all())->all())->toBe([
        'Today' => ['Today’s notification'],
        'Yesterday' => ['Yesterday’s notification'],
        'Older' => ['Old notification'],
    ]);

    $page->assertSeeInOrder(['Today', 'Today’s notification', 'Yesterday', 'Yesterday’s notification', 'Older', 'Old notification']);
});

test('the panel has an empty message for all and for unread only', function () {
    $user = User::factory()->create();

    Livewire::test('pages::dashboard', ['user' => $user])
        ->assertSee('No notifications yet.')
        ->toggle('onlyUnread')
        ->assertSee('You’re all caught up.');
});

test('a notification can be marked as read and back to unread', function () {
    $user = User::factory()->create();
    $notification = notifyUser($user, 'A notification');

    $page = Livewire::test('pages::dashboard', ['user' => $user])
        ->assertSeeHtml('aria-label="Mark as read"')
        ->call('markAsRead', $notification->id)
        ->assertSeeHtml('aria-label="Mark as unread"');

    expect($notification->fresh()->read())->toBeTrue()
        ->and($page->get('counts'))->toBe(['all' => 1, 'unread' => 0, 'read' => 1]);

    $page->call('markAsUnread', $notification->id);

    expect($notification->fresh()->unread())->toBeTrue()
        ->and($page->get('counts'))->toBe(['all' => 1, 'unread' => 1, 'read' => 0]);
});

test('another user’s notification cannot be marked', function (string $action) {
    $user = User::factory()->create();
    $otherNotification = notifyUser(User::factory()->create(), 'Not yours', isRead: $action === 'markAsUnread');

    Livewire::test('pages::dashboard', ['user' => $user])
        ->call($action, $otherNotification->id)
        ->assertNotFound();

    expect($otherNotification->fresh()->read())->toBe($action === 'markAsUnread');
})->with(['markAsRead', 'markAsUnread']);

test('mark all as read marks only this user’s notifications and shows a toast', function () {
    $user = User::factory()->create();
    notifyUser($user, 'First');
    notifyUser($user, 'Second');
    $otherNotification = notifyUser(User::factory()->create(), 'Not yours');

    Livewire::test('pages::dashboard', ['user' => $user])
        ->call('markAllAsRead')
        ->assertDispatched('toast', type: 'success', message: 'All notifications marked as read.')
        ->assertDontSeeHtml('aria-label="Mark as read"')
        ->toggle('onlyUnread')
        ->assertSee('You’re all caught up.');

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($otherNotification->fresh()->unread())->toBeTrue();
});

test('mark all as read is disabled and silent with nothing unread', function () {
    $user = User::factory()->create();
    notifyUser($user, 'Read', isRead: true);

    $page = Livewire::test('pages::dashboard', ['user' => $user]);

    expect($page->html())->toMatch('/wire:click="markAllAsRead"\s+disabled/');

    $page->call('markAllAsRead')
        ->assertNotDispatched('toast');
});

test('a poll picks up new notifications and updates the counts', function () {
    $user = User::factory()->create();
    notifyUser($user, 'Existing notification', isRead: true);

    $page = Livewire::test('pages::dashboard', ['user' => $user])
        ->assertDontSee('Fresh notification');

    $user->notify(new ActivityNotification('Fresh notification', 'Arrived between polls.'));

    $page->refresh()
        ->assertSeeInOrder(['Fresh notification', 'Existing notification'])
        ->assertSee('Arrived between polls.');

    expect($page->get('counts'))->toBe(['all' => 2, 'unread' => 1, 'read' => 1]);
});

test('the seeder creates 8 notifications for the first user, 3 unread', function () {
    $user = User::factory()->create();

    $this->seed(NotificationSeeder::class);

    expect($user->notifications()->count())->toBe(8)
        ->and($user->unreadNotifications()->count())->toBe(3);
});
