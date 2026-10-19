<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-test-notification {user=1 : The ID of the user to notify} {--title=New activity : The notification title} {--message=Something happened in your workspace. : The notification message}')]
#[Description('Send a database notification to a user, to see the dashboard pick it up on its next poll')]
class SendTestNotification extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::find($this->argument('user'));

        if ($user === null) {
            $this->error("User {$this->argument('user')} not found.");

            return self::FAILURE;
        }

        $user->notify(new ActivityNotification($this->option('title'), $this->option('message')));

        $this->info("Notification sent to {$user->name}.");

        return self::SUCCESS;
    }
}
