<?php

namespace App\Enums;

enum PostCategory: string
{
    case Engineering = 'engineering';
    case Design = 'design';
    case Product = 'product';
    case Culture = 'culture';
    case News = 'news';

    /**
     * Get the human readable label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Engineering => 'Engineering',
            self::Design => 'Design',
            self::Product => 'Product',
            self::Culture => 'Culture',
            self::News => 'News',
        };
    }
}
