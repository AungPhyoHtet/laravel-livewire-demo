<?php

use App\Models\Meeting;
use Database\Seeders\MeetingSeeder;
use Livewire\Livewire;

test('the page shows the meeting title, date and notes', function () {
    $meeting = Meeting::factory()->create([
        'title' => 'Sprint planning',
        'date' => '2026-10-20',
        'notes' => 'Review the backlog.',
    ]);

    $this->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertSee('Sprint planning')
        ->assertSee('2026-10-20')
        ->assertSee('Review the backlog.');
});

test('the title is sent to the server when the user leaves it', function () {
    $meeting = Meeting::factory()->create();

    $this->get(route('meetings.show', $meeting))
        ->assertSee('wire:model.live.blur="title"', false);
});

test('editing the notes saves them to the meeting', function () {
    $meeting = Meeting::factory()->create();

    Livewire::test('pages::meeting-notes', ['meeting' => $meeting])
        ->set('notes', 'Decided to ship on Friday.')
        ->assertHasNoErrors();

    expect($meeting->fresh()->notes)->toBe('Decided to ship on Friday.');
});

test('editing the title and date saves them to the meeting', function () {
    $meeting = Meeting::factory()->create();

    Livewire::test('pages::meeting-notes', ['meeting' => $meeting])
        ->set('title', 'Retro')
        ->set('date', '2026-11-02')
        ->assertHasNoErrors();

    expect($meeting->fresh())
        ->title->toBe('Retro')
        ->date->toDateString()->toBe('2026-11-02');
});

test('an empty title is not saved', function () {
    $meeting = Meeting::factory()->create(['title' => 'Original title']);

    Livewire::test('pages::meeting-notes', ['meeting' => $meeting])
        ->set('title', '')
        ->assertHasErrors(['title' => 'required']);

    expect($meeting->fresh()->title)->toBe('Original title');
});

test('a missing meeting returns 404', function () {
    $this->get('/meetings/999')->assertNotFound();
});

test('the seeder creates a meeting with notes', function () {
    $this->seed(MeetingSeeder::class);

    expect(Meeting::sole())
        ->title->toBe('Weekly Product Sync')
        ->notes->toContain('Action items');
});
