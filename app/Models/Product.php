<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property ProductCategory $category
 * @property string|null $description
 * @property string $price
 * @property string|null $url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'category', 'description', 'price', 'url'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'price' => 'decimal:2',
        ];
    }

    /**
     * Get the price formatted for display, e.g. "$1,234.50".
     */
    public function formattedPrice(): string
    {
        return '$'.number_format((float) $this->price, 2);
    }
}
