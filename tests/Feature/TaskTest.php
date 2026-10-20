<?php

use App\Enums\TaskStatus;
use App\Models\Task;

/**
 * Create tasks in a column, in the given order.
 *
 * @param  array<int, string>  $titles
 * @return array<int, Task>
 */
function column(TaskStatus $status, array $titles): array
{
    return array_map(
        fn (string $title, int $position): Task => Task::factory()->create(['title' => $title, 'status' => $status, 'position' => $position]),
        $titles,
        array_keys($titles),
    );
}

/**
 * Get the titles in a column, in board order.
 *
 * @return array<int, string>
 */
function columnTitles(TaskStatus $status): array
{
    return Task::query()->where('status', $status)->orderBy('position')->pluck('title')->all();
}

test('a task casts its status and position', function () {
    $task = Task::factory()->create(['status' => TaskStatus::CodeMerged, 'position' => '3']);

    expect($task->fresh())
        ->status->toBe(TaskStatus::CodeMerged)
        ->position->toBe(3);
});

test('a task can be moved within its column', function () {
    [$first] = column(TaskStatus::ToDo, ['First', 'Second', 'Third']);

    $first->moveTo(TaskStatus::ToDo, 2);

    expect(columnTitles(TaskStatus::ToDo))->toBe(['Second', 'Third', 'First'])
        ->and(Task::query()->where('status', TaskStatus::ToDo)->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2]);
});

test('a task moved to another column closes the gap it leaves', function () {
    [, $second] = column(TaskStatus::ToDo, ['First', 'Second', 'Third']);
    column(TaskStatus::Testing, ['Testing one', 'Testing two']);

    $second->moveTo(TaskStatus::Testing, 1);

    expect($second->fresh()->status)->toBe(TaskStatus::Testing)
        ->and(columnTitles(TaskStatus::Testing))->toBe(['Testing one', 'Second', 'Testing two'])
        ->and(columnTitles(TaskStatus::ToDo))->toBe(['First', 'Third'])
        ->and(Task::query()->where('status', TaskStatus::ToDo)->orderBy('position')->pluck('position')->all())->toBe([0, 1]);
});

test('a position outside the column is clamped', function (int $position, array $expectedTitles) {
    [$task] = column(TaskStatus::ToDo, ['Moving']);
    column(TaskStatus::Done, ['Done one', 'Done two']);

    $task->moveTo(TaskStatus::Done, $position);

    expect(columnTitles(TaskStatus::Done))->toBe($expectedTitles);
})->with([
    'past the end' => [99, ['Done one', 'Done two', 'Moving']],
    'negative' => [-5, ['Moving', 'Done one', 'Done two']],
]);
