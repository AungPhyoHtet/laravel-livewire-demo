<?php

use App\Models\Meeting;
use Carbon\CarbonImmutable;

test('a meeting can be created with a title, date and notes', function () {
    $meeting = Meeting::create([
        'title' => 'Sprint planning',
        'date' => '2026-10-20',
        'notes' => 'Review the backlog.',
    ]);

    expect($meeting->fresh())
        ->title->toBe('Sprint planning')
        ->date->toBeInstanceOf(CarbonImmutable::class)
        ->date->toDateString()->toBe('2026-10-20')
        ->notes->toBe('Review the backlog.');
});

test('notes are optional', function () {
    $meeting = Meeting::factory()->create(['notes' => null]);

    expect($meeting->fresh()->notes)->toBeNull();
});
