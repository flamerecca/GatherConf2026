<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['uuid', 'user_id', 'scores', 'type_code', 'unique_bunny_path', 'unique_bunny_traits', 'completed_at'])]
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
            'unique_bunny_traits' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The URL of the bunny drawn for this attempt, or null while it is not generated yet.
     */
    public function uniqueBunnyUrl(): ?string
    {
        return $this->unique_bunny_path === null ? null : Storage::disk('public')->url($this->unique_bunny_path);
    }

    /**
     * Whether a unique bunny is still expected to arrive for this attempt.
     */
    public function isAwaitingUniqueBunny(): bool
    {
        return $this->unique_bunny_path === null
            && filled(config('services.gemini.key'))
            && $this->completed_at->gt(now()->subMinutes(5));
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
