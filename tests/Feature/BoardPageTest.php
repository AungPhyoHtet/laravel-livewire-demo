<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use Database\Seeders\TaskSeeder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('the board shows every column in order with its task count', function () {
    Task::factory()->count(2)->create(['status' => TaskStatus::InProgress]);
    Task::factory()->create(['status' => TaskStatus::Done]);

    $this->get(route('board'))
        ->assertOk()
        ->assertSee('Board')
        ->assertSeeInOrder(['To do', 'In progress', 'Reopen', 'Code merged', 'Testing', 'Done']);

    $columns = Livewire::test('pages::board')->get('columns');

    expect($columns->keys()->all())->toBe(['to_do', 'in_progress', 'reopen', 'code_merged', 'testing', 'done'])
        ->and($columns->map->count()->all())->toBe(['to_do' => 0, 'in_progress' => 2, 'reopen' => 0, 'code_merged' => 0, 'testing' => 0, 'done' => 1]);
});

test('the cards are listed by position within their column', function () {
    Task::factory()->create(['title' => 'Second card', 'status' => TaskStatus::Reopen, 'position' => 1, 'description' => 'More detail.']);
    Task::factory()->create(['title' => 'First card', 'status' => TaskStatus::Reopen, 'position' => 0]);

    Livewire::test('pages::board')
        ->assertSeeInOrder(['Reopen', 'First card', 'Second card', 'More detail.', 'Code merged']);
});

test('every column is a drag and drop group and every card a focusable sort item', function () {
    $task = Task::factory()->create(['status' => TaskStatus::Testing]);

    Livewire::test('pages::board')
        ->assertSeeHtml('wire:sort="moveTask"')
        ->assertSeeHtml('wire:sort:group="tasks"')
        ->assertSeeHtml('wire:sort:group-id="testing"')
        ->assertSeeHtml('wire:sort:item="'.$task->id.'"')
        ->assertSeeHtml('data-task-id="'.$task->id.'"')
        ->assertSeeHtml('tabindex="0"')
        ->assertSeeHtml('aria-describedby="board-instructions"');
});

test('the board handles the arrow keys and lists the shortcuts', function () {
    Livewire::test('pages::board')
        ->assertSeeHtml('x-on:keydown="handleKeydown($event)"')
        ->assertSeeHtml('{ ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [-1, 0], ArrowRight: [1, 0] }')
        ->assertSeeHtml('if (event.shiftKey)')
        ->assertSee('to move focus')
        ->assertSee('+ arrows to move the card');
});

test('dropping a card moves it and announces where it went', function () {
    $moving = Task::factory()->create(['title' => 'Fix login', 'status' => TaskStatus::ToDo]);
    Task::factory()->create(['title' => 'Already testing', 'status' => TaskStatus::Testing, 'position' => 0]);

    $page = Livewire::test('pages::board')
        ->call('moveTask', $moving->id, 0, 'testing')
        ->assertSet('announcement', 'Moved “Fix login” to Testing, position 1 of 2.');

    expect($moving->fresh()->status)->toBe(TaskStatus::Testing)
        ->and($page->get('columns')->get('testing')->pluck('title')->all())->toBe(['Fix login', 'Already testing'])
        ->and($page->get('columns')->get('to_do'))->toBeEmpty();

    $page->assertSee('Moved “Fix login” to Testing, position 1 of 2.');
});

test('a card can be reordered within its column', function () {
    $first = Task::factory()->create(['title' => 'First', 'status' => TaskStatus::Done, 'position' => 0]);
    Task::factory()->create(['title' => 'Second', 'status' => TaskStatus::Done, 'position' => 1]);

    $page = Livewire::test('pages::board')->call('moveTask', $first->id, 1, 'done');

    expect($page->get('columns')->get('done')->pluck('title')->all())->toBe(['Second', 'First']);
});

test('an unknown status is rejected', function () {
    $task = Task::factory()->create(['status' => TaskStatus::ToDo]);

    Livewire::test('pages::board')
        ->call('moveTask', $task->id, 0, 'archived')
        ->assertStatus(422);

    expect($task->fresh()->status)->toBe(TaskStatus::ToDo);
});

test('a missing task returns 404', function () {
    Livewire::test('pages::board')
        ->call('moveTask', 999, 0, 'done')
        ->assertNotFound();
});

test('the seeder fills every column', function () {
    $this->seed(TaskSeeder::class);

    expect(Task::count())->toBe(16)
        ->and(Task::query()->distinct()->pluck('status')->count())->toBe(count(TaskStatus::cases()));
});

test('a new task opens the form in the chosen column', function () {
    Livewire::test('pages::board')
        ->assertSet('isFormOpen', false)
        ->call('createTask', 'testing')
        ->assertSet('isFormOpen', true)
        ->assertSet('editingTaskId', null)
        ->assertSet('status', 'testing')
        ->assertSee('New task')
        ->assertSee('Create task');
});

test('an unknown column for a new task falls back to to do', function () {
    Livewire::test('pages::board')
        ->call('createTask', 'archived')
        ->assertSet('status', 'to_do');
});

test('a task is created at the end of its column', function () {
    Task::factory()->create(['title' => 'Existing', 'status' => TaskStatus::Reopen, 'position' => 0]);

    $page = Livewire::test('pages::board')
        ->call('createTask', 'reopen')
        ->set('title', '  Fix the flaky test ')
        ->set('description', '  ')
        ->call('saveTask')
        ->assertHasNoErrors()
        ->assertSet('isFormOpen', false)
        ->assertSet('title', '')
        ->assertDispatched('toast', type: 'success', message: 'Task created.')
        ->assertDispatched('task-saved')
        ->assertSet('announcement', 'Created “Fix the flaky test” in Reopen.');

    $task = Task::query()->where('title', 'Fix the flaky test')->sole();

    expect($task)
        ->status->toBe(TaskStatus::Reopen)
        ->position->toBe(1)
        ->description->toBeNull()
        ->and($page->get('columns')->get('reopen')->pluck('title')->all())->toBe(['Existing', 'Fix the flaky test']);
});

test('the task form is validated', function (array $fields, array $errors) {
    Livewire::test('pages::board')
        ->call('createTask')
        ->set($fields)
        ->call('saveTask')
        ->assertHasErrors($errors)
        ->assertSet('isFormOpen', true);

    expect(Task::count())->toBe(0);
})->with([
    'missing title' => [['title' => ''], ['title' => 'required']],
    'long title' => [['title' => str_repeat('a', 256)], ['title' => 'max']],
    'long description' => [['title' => 'Valid', 'description' => str_repeat('a', 2001)], ['description' => 'max']],
    'unknown column' => [['title' => 'Valid', 'status' => 'archived'], ['status']],
]);

test('editing a task fills the form with its details', function () {
    $task = Task::factory()->create(['title' => 'Old title', 'description' => 'Old detail.', 'status' => TaskStatus::Testing]);

    Livewire::test('pages::board')
        ->call('editTask', $task->id)
        ->assertSet('isFormOpen', true)
        ->assertSet('editingTaskId', $task->id)
        ->assertSet('title', 'Old title')
        ->assertSet('description', 'Old detail.')
        ->assertSet('status', 'testing')
        ->assertSee("Edit task #{$task->id}")
        ->assertSee('Save changes')
        ->assertSeeHtml('wire:click="deleteTask('.$task->id.')"')
        ->assertSeeHtml('wire:confirm="Delete this task? This can’t be undone."');
});

test('a task can be updated in place', function () {
    $task = Task::factory()->create(['title' => 'Old title', 'status' => TaskStatus::Testing, 'position' => 0]);
    Task::factory()->create(['title' => 'Below', 'status' => TaskStatus::Testing, 'position' => 1]);

    Livewire::test('pages::board')
        ->call('editTask', $task->id)
        ->set('title', 'New title')
        ->set('description', 'New detail.')
        ->call('saveTask')
        ->assertHasNoErrors()
        ->assertSet('isFormOpen', false)
        ->assertDispatched('toast', type: 'success', message: 'Task updated.')
        ->assertDispatched('task-saved', id: $task->id);

    expect($task->fresh())
        ->title->toBe('New title')
        ->description->toBe('New detail.')
        ->status->toBe(TaskStatus::Testing)
        ->position->toBe(0);
});

test('changing a task’s column moves it to the end of that column', function () {
    $task = Task::factory()->create(['title' => 'Moving', 'status' => TaskStatus::ToDo, 'position' => 0]);
    Task::factory()->create(['title' => 'Staying', 'status' => TaskStatus::ToDo, 'position' => 1]);
    Task::factory()->create(['title' => 'Already done', 'status' => TaskStatus::Done, 'position' => 0]);

    $page = Livewire::test('pages::board')
        ->call('editTask', $task->id)
        ->set('status', 'done')
        ->call('saveTask');

    expect($page->get('columns')->get('done')->pluck('title')->all())->toBe(['Already done', 'Moving'])
        ->and(Task::query()->where('title', 'Staying')->value('position'))->toBe(0);
});

test('the editing task id cannot be changed from the browser', function () {
    $task = Task::factory()->create();

    Livewire::test('pages::board')
        ->call('editTask', $task->id)
        ->set('editingTaskId', 999);
})->throws(CannotUpdateLockedPropertyException::class);

test('a task can be deleted and the gap in its column is closed', function () {
    Task::factory()->create(['title' => 'First', 'status' => TaskStatus::InProgress, 'position' => 0]);
    $task = Task::factory()->create(['title' => 'Delete me', 'status' => TaskStatus::InProgress, 'position' => 1]);
    Task::factory()->create(['title' => 'Last', 'status' => TaskStatus::InProgress, 'position' => 2]);

    Livewire::test('pages::board')
        ->call('deleteTask', $task->id)
        ->assertDispatched('toast', type: 'success', message: 'Task deleted.')
        ->assertSet('announcement', 'Deleted “Delete me”.')
        ->assertDontSeeHtml('data-task-id="'.$task->id.'"');

    expect(Task::find($task->id))->toBeNull()
        ->and(Task::query()->where('status', TaskStatus::InProgress)->orderBy('position')->pluck('position', 'title')->all())->toBe(['First' => 0, 'Last' => 1]);
});

test('deleting the task being edited closes the form', function () {
    $task = Task::factory()->create();

    Livewire::test('pages::board')
        ->call('editTask', $task->id)
        ->call('deleteTask', $task->id)
        ->assertSet('isFormOpen', false)
        ->assertSet('editingTaskId', null);
});

test('editing or deleting a missing task returns 404', function (string $action) {
    Livewire::test('pages::board')
        ->call($action, 999)
        ->assertNotFound();
})->with(['editTask', 'deleteTask']);

test('the form can be closed without saving', function () {
    $task = Task::factory()->create(['title' => 'Unchanged']);

    Livewire::test('pages::board')
        ->call('editTask', $task->id)
        ->set('title', 'Changed')
        ->call('closeForm')
        ->assertSet('isFormOpen', false)
        ->assertSet('title', '');

    expect($task->fresh()->title)->toBe('Unchanged');
});

test('the board offers buttons and keyboard shortcuts to create, edit and delete', function () {
    $task = Task::factory()->create(['status' => TaskStatus::Testing]);

    Livewire::test('pages::board')
        ->assertSeeHtml('wire:click="createTask(\'testing\')"')
        ->assertSeeHtml('wire:click="editTask('.$task->id.')"')
        ->assertSeeHtml("event.key === 'Enter'")
        ->assertSeeHtml("event.key === 'Delete' || event.key === 'Backspace'")
        ->assertSeeHtml("event.key.toLowerCase() === 'n'")
        ->assertSee('to edit')
        ->assertSee('to delete')
        ->assertSee('for a new task');
});
