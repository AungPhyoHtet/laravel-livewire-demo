<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    /**
     * The last change, read out to screen readers.
     */
    public string $announcement = '';

    public bool $isFormOpen = false;

    /**
     * The task being edited, or null while creating one.
     */
    #[Locked]
    public ?int $editingTaskId = null;

    public string $title = '';

    public ?string $description = null;

    public string $status = 'to_do';

    /**
     * Get the validation rules for the task form.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ];
    }

    /**
     * Open the form for a new task in the given column.
     */
    public function createTask(string $status = 'to_do'): void
    {
        $this->resetForm();

        $this->status = (TaskStatus::tryFrom($status) ?? TaskStatus::ToDo)->value;
        $this->isFormOpen = true;
    }

    /**
     * Open the form filled in with the task's details.
     */
    public function editTask(int $id): void
    {
        $task = Task::findOrFail($id);

        $this->resetForm();

        $this->editingTaskId = $task->id;
        $this->title = $task->title;
        $this->description = $task->description;
        $this->status = $task->status->value;
        $this->isFormOpen = true;
    }

    /**
     * Create the task at the end of its column, or update it and move it to the end of a new column.
     */
    public function saveTask(): void
    {
        $validated = $this->validate();
        $status = TaskStatus::from($validated['status']);
        $details = ['title' => trim($validated['title']), 'description' => trim($validated['description'] ?? '') ?: null];

        if ($this->editingTaskId === null) {
            $task = Task::create([...$details, 'status' => $status, 'position' => Task::query()->where('status', $status)->count()]);

            $message = 'Task created.';
            $this->announcement = "Created “{$task->title}” in {$status->label()}.";
        } else {
            $task = Task::findOrFail($this->editingTaskId);
            $task->update($details);

            if ($task->status !== $status) {
                $task->moveTo($status, PHP_INT_MAX);
            }

            $message = 'Task updated.';
            $this->announcement = "Updated “{$task->title}”.";
        }

        $this->closeForm();

        $this->dispatch('task-saved', id: $task->id);
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /**
     * Delete the task and close the gap it leaves in its column.
     */
    public function deleteTask(int $id): void
    {
        $task = Task::findOrFail($id);

        $task->delete();
        Task::renumber($task->status);

        if ($this->editingTaskId === $task->id) {
            $this->closeForm();
        }

        $this->announcement = "Deleted “{$task->title}”.";
        $this->dispatch('toast', type: 'success', message: 'Task deleted.');
    }

    public function closeForm(): void
    {
        $this->resetForm();

        $this->isFormOpen = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingTaskId', 'title', 'description', 'status']);
        $this->resetValidation();
    }

    /**
     * Move a task to a position in a column, from a drag and drop or a Shift + arrow key press.
     */
    public function moveTask(int $id, int $position, string $status): void
    {
        $task = Task::findOrFail($id);
        $targetStatus = TaskStatus::tryFrom($status);

        abort_if($targetStatus === null, 422);

        $task->moveTo($targetStatus, $position);

        $columnCount = Task::query()->where('status', $targetStatus)->count();

        $this->announcement = "Moved “{$task->title}” to {$targetStatus->label()}, position " . ($task->position + 1) . " of {$columnCount}.";
    }

    /**
     * Get the tasks for every column in board order, keyed by status value.
     *
     * @return Collection<string, Collection<int, Task>>
     */
    #[Computed]
    public function columns(): Collection
    {
        $tasks = Task::query()->orderBy('position')->orderBy('id')->get()->groupBy(fn(Task $task): string => $task->status->value);

        return collect(TaskStatus::cases())->mapWithKeys(
            fn(TaskStatus $status): array => [
                $status->value => $tasks->get($status->value, collect()),
            ],
        );
    }
};
?>

<div
    x-data="{
        pendingFocusId: null,
        columns() {
            return Array.from(this.$root.querySelectorAll('[data-column]'));
        },
        cardsIn(column) {
            return Array.from(column.querySelectorAll('[data-task-id]'));
        },
        focusTask(taskId) {
            this.$root.querySelector('[data-task-id=&quot;' + taskId + '&quot;]')?.focus();
        },
        rememberFocus(element) {
            if (!element.closest('[role=dialog]')) {
                this.$root._lastFocused = element;
            }
        },
        restoreFocus() {
            requestAnimationFrame(() => {
                const lastFocused = this.$root._lastFocused;

                if (this.pendingFocusId) {
                    this.focusTask(this.pendingFocusId);
                } else if (lastFocused?.isConnected) {
                    lastFocused.focus();
                }

                this.pendingFocusId = null;
            });
        },
        async handleKeydown(event) {
            const card = event.target;
            const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(card.tagName) || card.isContentEditable;
            const hasModifier = event.metaKey || event.ctrlKey || event.altKey;

            if (event.key.toLowerCase() === 'n' && !isTyping && !hasModifier && !this.$wire.isFormOpen) {
                event.preventDefault();
                this.$wire.createTask(card.closest('[data-column-section]')?.dataset.columnSection ?? 'to_do');

                return;
            }

            if (!card.dataset.taskId) {
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                this.$wire.editTask(Number(card.dataset.taskId));

                return;
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                await this.deleteCard(card);

                return;
            }

            const step = { ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [-1, 0], ArrowRight: [1, 0] } [event.key];

            if (!step) {
                return;
            }

            event.preventDefault();

            const columns = this.columns();
            const column = card.closest('[data-column]');
            const columnIndex = columns.indexOf(column);
            const cards = this.cardsIn(column);
            const index = cards.indexOf(card);
            const [columnStep, rowStep] = step;

            if (event.shiftKey) {
                await this.moveCard(card, columns[columnIndex + columnStep], columnStep === 0 ? index + rowStep : index, cards.length);
            } else if (rowStep !== 0) {
                cards[index + rowStep]?.focus();
            } else {
                this.focusNearestCard(columns, columnIndex, columnStep, index);
            }
        },
        async moveCard(card, targetColumn, position, columnLength) {
            const isSameColumn = targetColumn === card.closest('[data-column]');

            if (!targetColumn || (isSameColumn && (position < 0 || position >= columnLength))) {
                return;
            }

            const taskId = card.dataset.taskId;
            const targetLength = this.cardsIn(targetColumn).length;

            await this.$wire.moveTask(Number(taskId), Math.min(position, targetLength), targetColumn.dataset.column);

            this.$nextTick(() => this.focusTask(taskId));
        },
        async deleteCard(card) {
            const title = card.querySelector('[data-task-title]').innerText;

            if (!confirm('Delete “' + title + '”? This can’t be undone.')) {
                return;
            }

            const section = card.closest('[data-column-section]');
            const cards = this.cardsIn(section);
            const index = cards.indexOf(card);
            const neighbourId = (cards[index + 1] ?? cards[index - 1])?.dataset.taskId;

            await this.$wire.deleteTask(Number(card.dataset.taskId));

            this.$nextTick(() => neighbourId ? this.focusTask(neighbourId) : section.querySelector('[data-add-task]')?.focus());
        },
        focusNearestCard(columns, columnIndex, columnStep, index) {
            for (let next = columnIndex + columnStep; next >= 0 && next < columns.length; next += columnStep) {
                const cards = this.cardsIn(columns[next]);

                if (cards.length > 0) {
                    cards[Math.min(index, cards.length - 1)].focus();

                    return;
                }
            }
        },
    }"
    x-on:keydown="handleKeydown($event)"
    x-on:focusin="rememberFocus($event.target)"
    x-on:task-saved.window="pendingFocusId = $event.detail.id"
    class="min-h-screen bg-stone-100 px-4 py-10 sm:px-8"
>
    <header class="mx-auto flex max-w-[110rem] flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <h1 class="text-3xl font-semibold text-stone-900">Board</h1>
            <p class="text-sm text-stone-500">Drag cards between columns, or use the keyboard.</p>
        </div>

        <div class="flex flex-col gap-3 sm:items-end">
            <button
                type="button"
                wire:click="createTask"
                class="cursor-pointer self-start rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2 sm:self-end"
            >New task</button>

            <ul aria-label="Keyboard shortcuts" class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-500">
                <li><kbd class="rounded border border-stone-300 bg-white px-1">Tab</kbd> to a card</li>
                <li><kbd class="rounded border border-stone-300 bg-white px-1">↑</kbd> <kbd class="rounded border border-stone-300 bg-white px-1">↓</kbd> <kbd
                        class="rounded border border-stone-300 bg-white px-1"
                    >←</kbd> <kbd class="rounded border border-stone-300 bg-white px-1">→</kbd> to move focus</li>
                <li><kbd class="rounded border border-stone-300 bg-white px-1">Shift</kbd> + arrows to move the card</li>
                <li><kbd class="rounded border border-stone-300 bg-white px-1">Enter</kbd> to edit</li>
                <li><kbd class="rounded border border-stone-300 bg-white px-1">Delete</kbd> to delete</li>
                <li><kbd class="rounded border border-stone-300 bg-white px-1">N</kbd> for a new task</li>
            </ul>
        </div>
    </header>

    <p id="board-instructions" class="sr-only">Use the arrow keys to move between cards. Hold Shift and press the up or down arrow to reorder the card, or the left or right arrow to move it to another
        column. Press Enter to edit the card, Delete to delete it, or N to add a task to this column.</p>

    <p aria-live="assertive" class="sr-only">{{ $announcement }}</p>

    <div class="relative mx-auto mt-8 flex max-w-[110rem] gap-4 overflow-x-auto pb-4">
        @foreach (TaskStatus::cases() as $columnStatus)
            @php($tasks = $this->columns->get($columnStatus->value))
            <section
                wire:key="column-{{ $columnStatus->value }}"
                data-column-section="{{ $columnStatus->value }}"
                aria-labelledby="column-heading-{{ $columnStatus->value }}"
                class="flex min-w-60 flex-1 flex-col rounded-lg bg-stone-200/60 p-3"
            >
                <h2 id="column-heading-{{ $columnStatus->value }}" class="flex items-center gap-2 px-1 text-sm font-semibold text-stone-700">
                    <span aria-hidden="true" class="size-2 rounded-full {{ $columnStatus->dotColor() }}"></span>
                    {{ $columnStatus->label() }}
                    <span class="ml-auto rounded-full bg-white px-2 text-xs font-medium text-stone-500">
                        {{ $tasks->count() }}
                        <span class="sr-only">{{ Str::plural('task', $tasks->count()) }}</span>
                    </span>
                </h2>

                <ul
                    data-column="{{ $columnStatus->value }}"
                    wire:sort="moveTask"
                    wire:sort:group="tasks"
                    wire:sort:group-id="{{ $columnStatus->value }}"
                    aria-labelledby="column-heading-{{ $columnStatus->value }}"
                    class="mt-3 flex min-h-24 flex-1 flex-col gap-2"
                >
                    @foreach ($tasks as $task)
                        <li
                            wire:key="task-{{ $task->id }}"
                            wire:sort:item="{{ $task->id }}"
                            data-task-id="{{ $task->id }}"
                            tabindex="0"
                            aria-describedby="board-instructions"
                            class="group cursor-grab rounded-md bg-white p-3 shadow-sm ring-1 ring-stone-200 hover:ring-stone-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 active:cursor-grabbing"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs font-medium text-stone-400">#{{ $task->id }}</p>
                                <button
                                    type="button"
                                    wire:sort:ignore
                                    wire:click="editTask({{ $task->id }})"
                                    tabindex="-1"
                                    aria-label="Edit “{{ $task->title }}”"
                                    class="-m-1 cursor-pointer rounded p-1 text-stone-300 opacity-0 hover:text-stone-600 group-hover:opacity-100 group-focus:opacity-100"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                        class="size-4"
                                    >
                                        <path
                                            d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z"
                                        />
                                        <path
                                            d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <p data-task-title class="mt-1 text-sm font-medium leading-5 text-stone-900">{{ $task->title }}</p>
                            @if ($task->description !== null)
                                <p class="mt-1 line-clamp-2 text-xs leading-5 text-stone-500">{{ $task->description }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <button
                    type="button"
                    data-add-task
                    wire:click="createTask('{{ $columnStatus->value }}')"
                    class="mt-2 flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 text-sm text-stone-500 hover:bg-stone-200 hover:text-stone-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                >
                    <span aria-hidden="true">+</span> Add task<span class="sr-only"> to {{ $columnStatus->label() }}</span>
                </button>
            </section>
        @endforeach
    </div>

    <div
        wire:show="isFormOpen"
        x-trap.noreturn.noscroll="$wire.isFormOpen"
        x-effect="$wire.isFormOpen || restoreFocus()"
        x-on:keydown.escape.window="$wire.isFormOpen && $wire.closeForm()"
        x-cloak
        class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto px-4 py-10 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="task-form-title"
    >
        <div
            wire:click="closeForm"
            class="fixed inset-0 bg-stone-900/40"
            aria-hidden="true"
        ></div>

        <form wire:submit="saveTask" class="relative w-full max-w-lg rounded-lg bg-white shadow-xl ring-1 ring-stone-200">
            <header class="flex items-start justify-between gap-4 border-b border-stone-200 px-6 pb-4 pt-5">
                <h2 id="task-form-title" class="text-lg font-semibold text-stone-900">{{ $editingTaskId === null ? 'New task' : "Edit task #{$editingTaskId}" }}</h2>

                <button
                    type="button"
                    wire:click="closeForm"
                    class="rounded p-1 text-stone-400 hover:text-stone-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300"
                    aria-label="Close"
                >
                    <svg
                        class="size-5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                        />
                    </svg>
                </button>
            </header>

            <div class="space-y-5 px-6 py-6">
                <div class="space-y-1.5">
                    <label for="task-title" class="block text-sm font-medium text-stone-700">Title</label>
                    <input
                        id="task-title"
                        type="text"
                        wire:model="title"
                        autofocus
                        placeholder="What needs doing?"
                        class="w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-base text-stone-900 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                    >
                    @error('title')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="task-description" class="block text-sm font-medium text-stone-700">
                        Description <span class="font-normal text-stone-400">(optional)</span>
                    </label>
                    <textarea
                        id="task-description"
                        wire:model="description"
                        rows="3"
                        placeholder="Add more detail…"
                        class="field-sizing-content min-h-24 w-full resize-none rounded-md border border-stone-200 bg-white px-3 py-2 text-base leading-7 text-stone-800 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                    ></textarea>
                    @error('description')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="task-status" class="block text-sm font-medium text-stone-700">Column</label>
                    <select
                        id="task-status"
                        wire:model="status"
                        class="w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-base text-stone-800 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                    >
                        @foreach (TaskStatus::cases() as $statusOption)
                            <option wire:key="status-{{ $statusOption->value }}" value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <footer class="flex items-center gap-3 border-t border-stone-200 px-6 py-4">
                @if ($editingTaskId !== null)
                    <button
                        type="button"
                        wire:click="deleteTask({{ $editingTaskId }})"
                        wire:confirm="Delete this task? This can’t be undone."
                        class="cursor-pointer rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-200"
                    >Delete</button>
                @endif

                <button
                    type="button"
                    wire:click="closeForm"
                    class="ml-auto cursor-pointer rounded-md px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300"
                >Cancel</button>
                <button type="submit"
                    class="cursor-pointer rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2"
                >{{ $editingTaskId === null ? 'Create task' : 'Save changes' }}</button>
            </footer>
        </form>
    </div>
</div>
