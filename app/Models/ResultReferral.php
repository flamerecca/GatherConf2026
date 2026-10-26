<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visitor who first reached the site through someone else's result page, tracked by a cookie.
 */
#[Fillable(['quiz_attempt_id', 'visitor_token', 'completed_at', 'registered_at'])]
class ResultReferral extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    public function quizAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class);
    }
}
