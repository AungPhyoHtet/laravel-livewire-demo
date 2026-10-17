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
        ->assertSee('Save')
        ->assertSee('x-on:toast.window', false);
});

test('editing a field does not save it until save is pressed', function () {
    $profile = Profile::factory()->create(['display_name' => 'Original name']);

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set('displayName', 'Sam Lee');

    expect($profile->fresh()->display_name)->toBe('Original name');
});

test('saving the display name, title and description stores them on the profile', function () {
    $profile = Profile::factory()->create();

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set('displayName', 'Sam Lee')
        ->set('title', 'Engineer')
        ->set('description', 'Builds things.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Profile settings saved.');

    expect($profile->fresh())
        ->display_name->toBe('Sam Lee')
        ->title->toBe('Engineer')
        ->description->toBe('Builds things.');
});

test('saving a theme and language stores them on the profile', function () {
    $profile = Profile::factory()->create([
        'theme' => Theme::Light,
        'language' => Language::English,
    ]);

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set('theme', 'dark')
        ->set('language', 'ja')
        ->call('save')
        ->assertHasNoErrors();

    expect($profile->fresh())
        ->theme->toBe(Theme::Dark)
        ->language->toBe(Language::Japanese);
});

test('an empty display name is not saved', function () {
    $profile = Profile::factory()->create(['display_name' => 'Original name']);

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set('displayName', '')
        ->call('save')
        ->assertHasErrors(['displayName' => 'required'])
        ->assertNotDispatched('toast');

    expect($profile->fresh()->display_name)->toBe('Original name');
});

test('an unknown theme or language is not saved', function () {
    $profile = Profile::factory()->create([
        'theme' => Theme::Light,
        'language' => Language::English,
    ]);

    Livewire::test('pages::profile-settings', ['profile' => $profile])
        ->set('theme', 'neon')
        ->set('language', 'xx')
        ->call('save')
        ->assertHasErrors(['theme', 'language'])
        ->assertNotDispatched('toast');

    expect($profile->fresh())
        ->theme->toBe(Theme::Light)
        ->language->toBe(Language::English);
});

test('a missing profile returns 404', function () {
    $this->get('/profiles/999')->assertNotFound();
});

test('the seeder creates a profile', function () {
    $this->seed(ProfileSeeder::class);

    expect(Profile::sole())
        ->display_name->toBe('Alex Rivera')
        ->title->toBe('Product Designer');
});
