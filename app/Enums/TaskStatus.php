<?php

namespace App\Enums;

enum TaskStatus: string
{
    case ToDo = 'to_do';
    case InProgress = 'in_progress';
    case Reopen = 'reopen';
    case CodeMerged = 'code_merged';
    case Testing = 'testing';
    case Done = 'done';

    /**
     * Get the human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::ToDo => 'To do',
            self::InProgress => 'In progress',
            self::Reopen => 'Reopen',
            self::CodeMerged => 'Code merged',
            self::Testing => 'Testing',
            self::Done => 'Done',
        };
    }

    /**
     * Get the background colour class for the status dot on its board column.
     */
    public function dotColor(): string
    {
        return match ($this) {
            self::ToDo => 'bg-stone-400',
            self::InProgress => 'bg-sky-500',
            self::Reopen => 'bg-rose-500',
            self::CodeMerged => 'bg-violet-500',
            self::Testing => 'bg-amber-500',
            self::Done => 'bg-emerald-500',
        };
    }
}
