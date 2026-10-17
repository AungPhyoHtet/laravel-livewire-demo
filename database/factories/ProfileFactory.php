<?php

namespace Database\Factories;

use App\Enums\Language;
use App\Enums\Theme;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => fake()->name(),
            'title' => fake()->optional()->jobTitle(),
            'description' => fake()->optional()->paragraph(),
            'theme' => fake()->randomElement(Theme::cases()),
            'language' => fake()->randomElement(Language::cases()),
        ];
    }
}
