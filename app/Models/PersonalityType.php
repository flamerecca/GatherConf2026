<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'summary', 'description', 'strengths', 'weaknesses', 'career_advice', 'growth_directions'])]
class PersonalityType extends Model
{
    use SoftDeletes;

    /**
     * The bunny illustration for this type, relative to the project root, or null when none was added yet.
     */
    public function bunnyImagePath(): ?string
    {
        $files = glob(resource_path('images/bunny/'.$this->code.'.{jpeg,jpg,png,webp}'), GLOB_BRACE);

        return $files ? 'resources/images/bunny/'.basename($files[0]) : null;
    }

    /**
     * The resized WebP copies of the bunny illustration, keyed by pixel width and valued by path relative to the project root.
     *
     * @return array<int, string>
     */
    public function bunnyThumbnailPaths(): array
    {
        $thumbnails = [];

        foreach (glob(resource_path('images/bunny/'.$this->code.'-*.webp')) ?: [] as $file) {
            $thumbnails[(int) preg_replace('/^.*-(\d+)\.webp$/', '$1', $file)] = 'resources/images/bunny/'.basename($file);
        }

        ksort($thumbnails);

        return $thumbnails;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strengths' => 'array',
            'weaknesses' => 'array',
            'growth_directions' => 'array',
        ];
    }
}
