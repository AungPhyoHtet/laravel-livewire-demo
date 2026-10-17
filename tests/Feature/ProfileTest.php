<?php

use App\Enums\Language;
use App\Enums\Theme;
use App\Models\Profile;

test('a profile can be created with a display name, title, description, theme and language', function () {
    $profile = Profile::create([
        'display_name' => 'Alex Rivera',
        'title' => 'Product Designer',
        'description' => 'Designing calm tools.',
        'theme' => Theme::Dark,
        'language' => Language::French,
    ]);

    expect($profile->fresh())
        ->display_name->toBe('Alex Rivera')
        ->title->toBe('Product Designer')
        ->description->toBe('Designing calm tools.')
        ->theme->toBe(Theme::Dark)
        ->language->toBe(Language::French);
});

test('title and description are optional', function () {
    $profile = Profile::factory()->create(['title' => null, 'description' => null]);

    expect($profile->fresh())
        ->title->toBeNull()
        ->description->toBeNull();
});
