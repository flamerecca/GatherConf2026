<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'summary', 'description', 'strengths', 'weaknesses'])]
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strengths' => 'array',
            'weaknesses' => 'array',
        ];
    }
}
