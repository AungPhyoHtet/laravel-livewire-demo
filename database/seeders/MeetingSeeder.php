<?php

namespace Database\Seeders;

use App\Models\Meeting;
use Illuminate\Database\Seeder;

class MeetingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Meeting::factory()->create([
            'title' => 'Weekly Product Sync',
            'date' => now()->toDateString(),
            'notes' => <<<'NOTES'
                Attendees: Product, Design, Engineering

                Agenda
                - Review last sprint's release
                - Prioritise the onboarding redesign
                - Open questions on the notifications API

                Discussion
                - Release went out on schedule; two minor bugs reported, both fixed.
                - Design shared new onboarding mockups. Team agreed to cut step 3.
                - Notifications API needs rate limiting before public launch.

                Action items
                - Design: finalise onboarding screens by Friday.
                - Engineering: add rate limiting to notifications API.
                - Product: update roadmap and share with stakeholders.
                NOTES,
        ]);
    }
}
