<?php

namespace App\Enums;

enum Language: string
{
    case English = 'en';
    case Spanish = 'es';
    case French = 'fr';
    case German = 'de';
    case Japanese = 'ja';

    /**
     * Get the human readable label for the language.
     */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Spanish => 'Español',
            self::French => 'Français',
            self::German => 'Deutsch',
            self::Japanese => '日本語',
        };
    }
}
