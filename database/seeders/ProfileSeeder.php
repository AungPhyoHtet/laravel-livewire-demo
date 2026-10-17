<?php

namespace Database\Seeders;

use App\Enums\Language;
use App\Enums\Theme;
use App\Models\Profile;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Profile::factory()->create([
            'display_name' => 'Alex Rivera',
            'title' => 'Product Designer',
            'description' => 'Designing calm, focused tools for teams. Currently working on onboarding and notifications.',
            'theme' => Theme::System,
            'language' => Language::English,
        ]);
    }
}
