<?php

use App\Models\User;

test('the command sends a database notification to the user', function () {
    $user = User::factory()->create(['name' => 'Alex Rivera']);

    $this->artisan('app:send-test-notification', [
        'user' => $user->id,
        '--title' => 'Build finished',
        '--message' => 'Deploy #42 is live.',
    ])
        ->expectsOutput('Notification sent to Alex Rivera.')
        ->assertSuccessful();

    $notification = $user->notifications()->sole();

    expect($notification->data)->toBe(['title' => 'Build finished', 'message' => 'Deploy #42 is live.'])
        ->and($notification->unread())->toBeTrue();
});

test('the command uses a default title and message', function () {
    $user = User::factory()->create();

    $this->artisan('app:send-test-notification', ['user' => $user->id])->assertSuccessful();

    expect($user->notifications()->sole()->data)
        ->toBe(['title' => 'New activity', 'message' => 'Something happened in your workspace.']);
});

test('the command fails for an unknown user', function () {
    $this->artisan('app:send-test-notification', ['user' => 999])
        ->expectsOutput('User 999 not found.')
        ->assertFailed();
});
