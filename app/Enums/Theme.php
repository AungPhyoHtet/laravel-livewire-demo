<?php

namespace App\Enums;

enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    /**
     * Get the human readable label for the theme.
     */
    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light',
            self::Dark => 'Dark',
            self::System => 'System',
        };
    }
}
