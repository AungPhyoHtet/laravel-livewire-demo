<?php

namespace Database\Seeders;

use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /**
     * Sample notifications as [title, message, hours ago, is read].
     *
     * @var array<int, array{0: string, 1: string, 2: int, 3: bool}>
     */
    protected const NOTIFICATIONS = [
        ['New comment on your post', 'Priya Shah commented on “Shipping Livewire 4 islands to production”.', 1, false],
        ['Product low on stock', 'Only 3 units of “Mechanical Keyboard” are left.', 3, false],
        ['Meeting notes updated', 'Tom Becker edited the notes for the weekly planning meeting.', 5, false],
        ['Weekly report is ready', 'Your weekly summary for posts and products is ready to view.', 20, true],
        ['New team member', 'Mina Okafor joined the workspace.', 30, true],
        ['Profile updated', 'Your display name was changed.', 52, true],
        ['Post published', '“Designing calm interfaces for busy people” is now live.', 75, true],
        ['Password changed', 'Your password was changed. If this wasn’t you, contact support.', 120, true],
    ];

    /**
     * Seed 8 notifications for the first user, 3 of them unread.
     */
    public function run(): void
    {
        $user = User::query()->oldest('id')->first() ?? User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        foreach (self::NOTIFICATIONS as [$title, $message, $hoursAgo, $isRead]) {
            $createdAt = now()->subHours($hoursAgo);

            $user->notifications()->create([
                'id' => Str::uuid()->toString(),
                'type' => ActivityNotification::class,
                'data' => ['title' => $title, 'message' => $message],
                'read_at' => $isRead ? $createdAt->copy()->addHour() : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
