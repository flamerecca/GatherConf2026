<?php

namespace App\Models;

use Database\Factories\QuizAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Fillable(['uuid', 'user_id', 'scores', 'max_scores', 'type_code', 'unique_bunny_path', 'unique_bunny_traits', 'completed_at'])]
class QuizAttempt extends Model
{
    /** @use HasFactory<QuizAttemptFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (QuizAttempt $attempt): void {
            $attempt->discardUniqueBunny();
        });

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
     * Soft delete the results nobody claimed, finished before the given moment, together with their unique bunnies.
     */
    public static function clearGuests(Carbon $before): int
    {
        $cleared = 0;

        static::query()
            ->whereNull('user_id')
            ->where('completed_at', '<', $before)
            ->each(function (QuizAttempt $attempt) use (&$cleared): void {
                $attempt->delete();
                $cleared++;
            });

        return $cleared;
    }

    /**
     * Remove the drawn bunny from its disk, leaving the type bunny as the only image of this result.
     */
    public function discardUniqueBunny(): void
    {
        if ($this->unique_bunny_path !== null) {
            Storage::disk(config('filesystems.unique_bunny_disk'))->delete($this->unique_bunny_path);
        }
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
            'max_scores' => 'array',
            'unique_bunny_traits' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The URL of the bunny drawn for this attempt, served by the app so the bucket can stay private, or null while it is not generated yet.
     */
    public function uniqueBunnyUrl(): ?string
    {
        return $this->unique_bunny_path === null ? null : route('quiz.result.bunny', $this);
    }

    /**
     * Whether a unique bunny is still expected to arrive for this attempt.
     *
     * The window is short on purpose: users cannot ask for a redraw, so an attempt without an
     * image after it closes stays that way and falls back to the type bunny.
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
