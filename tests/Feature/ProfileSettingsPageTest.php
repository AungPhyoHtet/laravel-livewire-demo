<?php

use App\Enums\Language;
use App\Enums\Theme;
use App\Models\Profile;
use Database\Seeders\ProfileSeeder;
use Livewire\Livewire;

test('the page shows the profile settings', function () {
    $profile = Profile::factory()->create([
        'display_name' => 'Alex Rivera',
        'title' => 'Product Designer',
        'description' => 'Designing calm tools.',
    ]);

    $this->get(route('profiles.edit', $profile))
        ->assertOk()
        ->assertSee('Alex Rivera')
        ->assertSee('Product Designer')
        ->assertSee('Designing calm tools.')
        ->assertSee('Display name')
        ->assertSee('Theme')
        ->assertSee('Language')
        ->assertSee('Changes are saved automatically.')
        ->assertSee('x-on:toast.window', false);
});

test('the page has no save button', function () {
    $profile = Profile::factory()->create();

    $this->get(route('profiles.edit', $profile))
        ->assertDontSee('type="submit"', false)
        ->assertDontSee('wire:submit', false);
});

test('text fields send their change to the server when the user leaves them', function () {
    $profile = Profile::factory()->create();

    $this->get(route('profiles.edit', $profile))
        ->assertSee('wire:model.live.blur="displayName"', false)
        ->assertSee('wire:model.live.blur="title"', false)
        ->assertSee('wire:model.live.blur="description"', false);
});

test('editing a text field saves it and shows a success toast', function (string $property, string $column, string $value, string $message) {
    $profile = Profile::factory()->create();

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set($property, $value)
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: $message);

    expect($profile->fresh()->{$column})->toBe($value);
})->with([
    'display name' => ['displayName', 'display_name', 'Sam Lee', 'Display name saved.'],
    'title' => ['title', 'title', 'Engineer', 'Title saved.'],
    'description' => ['description', 'description', 'Builds things.', 'Description saved.'],
]);

test('editing one field leaves the others unchanged', function () {
    $profile = Profile::factory()->create([
        'display_name' => 'Alex Rivera',
        'title' => 'Product Designer',
    ]);

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set('title', 'Engineer');

    expect($profile->fresh())
        ->display_name->toBe('Alex Rivera')
        ->title->toBe('Engineer');
});

test('choosing a theme saves it and shows an info toast', function () {
    $profile = Profile::factory()->create(['theme' => Theme::Light]);

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set('theme', 'dark')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'info', message: 'Theme changed to Dark.');

    expect($profile->fresh()->theme)->toBe(Theme::Dark);
});

test('choosing a language saves it and shows an info toast', function () {
    $profile = Profile::factory()->create(['language' => Language::English]);

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set('language', 'ja')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'info', message: 'Language changed to 日本語.');

    expect($profile->fresh()->language)->toBe(Language::Japanese);
});

test('an empty display name is not saved and shows an error toast', function () {
    $profile = Profile::factory()->create(['display_name' => 'Original name']);

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set('displayName', '')
        ->assertHasErrors(['displayName' => 'required'])
        ->assertDispatched('toast', type: 'error', message: 'The display name field is required.');

    expect($profile->fresh()->display_name)->toBe('Original name');
});

test('an unknown theme or language is not saved and shows an error toast', function (string $property) {
    $profile = Profile::factory()->create([
        'theme' => Theme::Light,
        'language' => Language::English,
    ]);

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set($property, 'unknown')
        ->assertHasErrors([$property])
        ->assertDispatched('toast', type: 'error');

    expect($profile->fresh())
        ->theme->toBe(Theme::Light)
        ->language->toBe(Language::English);
})->with(['theme', 'language']);

test('a missing profile returns 404', function () {
    $this->get('/profiles/999')->assertNotFound();
});

test('the seeder creates a profile', function () {
    $this->seed(ProfileSeeder::class);

    expect(Profile::sole())
        ->display_name->toBe('Alex Rivera')
        ->title->toBe('Product Designer');
});
