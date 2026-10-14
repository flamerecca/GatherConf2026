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

    protected static function booted(): void
    {
        static::saved(function (QuizAttempt $attempt): void {
            if ($attempt->user_id === null || $attempt->completed_at === null) {
                return;
            }

            $member = $attempt->member;

            if ($member === null) {
                return;
            }

            CarrotLedger::awardQuietly(
                $member,
                'complete_quiz',
                CarrotLedger::siteRewards()['complete_quiz'] ?? 0,
                CarrotLedger::siteDedupeKey('complete_quiz', $member),
            );
        });
    }

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

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'user_id');
    }
}
