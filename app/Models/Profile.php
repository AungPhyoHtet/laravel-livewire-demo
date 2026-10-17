<?php

namespace App\Models;

use App\Enums\Language;
use App\Enums\Theme;
use Carbon\CarbonImmutable;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $display_name
 * @property string|null $title
 * @property string|null $description
 * @property Theme $theme
 * @property Language $language
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['display_name', 'title', 'description', 'theme', 'language'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => Theme::class,
            'language' => Language::class,
        ];
    }
}
