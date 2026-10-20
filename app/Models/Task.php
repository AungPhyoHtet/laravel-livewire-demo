<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'description', 'status', 'position'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'position' => 'integer',
        ];
    }

    /**
     * Move the task to the zero-based position in the status's column, renumbering both columns.
     *
     * The position is clamped to the column, so a position past the end puts the task last.
     */
    public function moveTo(TaskStatus $status, int $position): void
    {
        DB::transaction(function () use ($status, $position): void {
            $previousStatus = $this->status;

            $column = static::query()
                ->where('status', $status)
                ->whereKeyNot($this->id)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $column->splice(max(0, min($position, $column->count())), 0, [$this]);

            $column->values()->each(fn (Task $task, int $index) => $task->fill([
                'status' => $status,
                'position' => $index,
            ])->save());

            if ($previousStatus !== $status) {
                static::renumber($previousStatus);
            }
        });
    }

    /**
     * Close the gaps in a column's positions, keeping its order.
     */
    public static function renumber(TaskStatus $status): void
    {
        static::query()
            ->where('status', $status)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(fn (Task $task, int $index) => $task->fill(['position' => $index])->save());
    }
}
