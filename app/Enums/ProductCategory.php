<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Electronics = 'electronics';
    case Clothing = 'clothing';
    case HomeAndKitchen = 'home_and_kitchen';
    case Books = 'books';
    case Sports = 'sports';
    case Other = 'other';

    /**
     * Get the human readable label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Electronics => 'Electronics',
            self::Clothing => 'Clothing',
            self::HomeAndKitchen => 'Home & Kitchen',
            self::Books => 'Books',
            self::Sports => 'Sports',
            self::Other => 'Other',
        };
    }
}
