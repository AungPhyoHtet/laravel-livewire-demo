<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Sample tasks as [title, description] grouped by status, in board order.
     *
     * @var array<string, array<int, array{0: string, 1: string|null}>>
     */
    protected const TASKS = [
        'to_do' => [
            ['Add CSV export to the products page', 'Export the filtered list with the same columns as the table.'],
            ['Rate limit the contact form', null],
            ['Show reading progress on long posts', 'A thin bar under the header that fills as you scroll.'],
            ['Archive meetings older than a year', null],
        ],
        'in_progress' => [
            ['Kanban board with keyboard support', 'Drag and drop between columns, and Shift + arrow keys to move cards.'],
            ['Upgrade to Tailwind 4.2', null],
            ['Profile avatars', 'Upload, crop and fall back to initials.'],
        ],
        'reopen' => [
            ['Notification badge stays after mark all as read', 'Reproduces when two tabs are open.'],
            ['Search ignores accented characters', null],
        ],
        'code_merged' => [
            ['Quick search for posts', 'Cmd/Ctrl + K palette with grouped, highlighted results.'],
            ['Tag autocomplete on products', null],
        ],
        'testing' => [
            ['Product wizard keeps draft between steps', 'Check refresh and back button on every step.'],
            ['Dashboard polls every 10 seconds', null],
        ],
        'done' => [
            ['Posts list with infinite scroll', null],
            ['Meeting notes autosave', 'Saves 2 seconds after typing stops.'],
            ['Seed 200 posts for local testing', null],
        ],
    ];

    /**
     * Seed 16 tasks spread across every board column.
     */
    public function run(): void
    {
        foreach (self::TASKS as $status => $tasks) {
            foreach ($tasks as $position => [$title, $description]) {
                Task::create([
                    'title' => $title,
                    'description' => $description,
                    'status' => TaskStatus::from($status),
                    'position' => $position,
                ]);
            }
        }
    }
}
