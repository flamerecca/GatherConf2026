<?php

namespace App\Models;

use Database\Factories\CommunityMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'community_slug', 'community_name', 'subject', 'body'])]
class CommunityMessage extends Model
{
    /** @use HasFactory<CommunityMessageFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_FORWARDED = 'forwarded';

    public const STATUS_CLOSED = 'closed';

    public const DAILY_LIMIT = 5;

    public const SUBJECT_LENGTH = 100;

    public const BODY_LENGTH = 1000;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @param  Builder<CommunityMessage>  $query
     * @return Builder<CommunityMessage>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public static function sentTodayBy(Member $member): int
    {
        return static::query()
            ->where('member_id', $member->getKey())
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    public function markAs(string $status, ?int $by): void
    {
        $reopened = $status === self::STATUS_PENDING;

        $this->forceFill([
            'status' => $status,
            'handled_by' => $reopened ? null : $by,
            'handled_at' => $reopened ? null : now(),
        ])->save();
    }
}
