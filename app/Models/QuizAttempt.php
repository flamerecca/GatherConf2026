<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'user_id', 'scores', 'type_code', 'completed_at'])]
class QuizAttempt extends Model
{
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function personalityType(): BelongsTo
    {
        return $this->belongsTo(PersonalityType::class, 'type_code', 'code');
    }
}
