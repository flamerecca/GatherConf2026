<?php

namespace App\Models;

use Database\Factories\DriftBottleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

#[Fillable(['sender_id', 'recipient_id', 'body'])]
class DriftBottle extends Model
{
    /** @use HasFactory<DriftBottleFactory> */
    use HasFactory, HasUuids;

    public const MAX_LENGTH = 300;

    public const HOLD_LIMIT = 5;

    public const DAILY_THROW_LIMIT = 3;

    public const DAILY_PICK_LIMIT = 10;

    public const RELEASE_AFTER_HOURS = 24;

    public const STATUS_IN_SEA = 'in_sea';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_OPENED = 'opened';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_REMOVED = 'removed';

    public const TARGET_BOTTLE = 'bottle';

    public const TARGET_REPLY = 'reply';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'opened_at' => 'datetime',
            'replied_at' => 'datetime',
            'removed_at' => 'datetime',
            'reply_removed_at' => 'datetime',
            'released_recipient_ids' => 'array',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'recipient_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DriftBottleReport::class, 'bottle_id');
    }

    public function status(): string
    {
        return match (true) {
            $this->removed_at !== null => self::STATUS_REMOVED,
            $this->replied_at !== null => self::STATUS_REPLIED,
            $this->opened_at !== null => self::STATUS_OPENED,
            $this->recipient_id !== null => self::STATUS_DELIVERED,
            default => self::STATUS_IN_SEA,
        };
    }

    /**
     * @param  Builder<DriftBottle>  $query
     * @return Builder<DriftBottle>
     */
    public function scopeInSea(Builder $query): Builder
    {
        return $query->whereNull('recipient_id')
            ->whereNull('opened_at')
            ->whereNull('removed_at');
    }

    /**
     * @param  Builder<DriftBottle>  $query
     * @return Builder<DriftBottle>
     */
    public function scopeReceivedBy(Builder $query, Member $member): Builder
    {
        return $query->where('recipient_id', $member->getKey())->whereNull('removed_at');
    }

    /**
     * @param  Builder<DriftBottle>  $query
     * @return Builder<DriftBottle>
     */
    public function scopeSentBy(Builder $query, Member $member): Builder
    {
        return $query->where('sender_id', $member->getKey());
    }

    public static function containsContactInfo(string $text): bool
    {
        return (bool) preg_match('~https?://|www\.|[^\s@]+@[^\s@]+\.[^\s@]+~iu', $text);
    }

    public static function throwsToday(Member $member): int
    {
        return static::query()
            ->where('sender_id', $member->getKey())
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    public static function heldCount(Member $member): int
    {
        return static::query()
            ->where('recipient_id', $member->getKey())
            ->whereNull('opened_at')
            ->whereNull('removed_at')
            ->count();
    }

    public static function pickKey(Member $member): string
    {
        return 'bottle-pick:'.$member->getKey();
    }

    public static function picksLeft(Member $member): int
    {
        return max(0, self::DAILY_PICK_LIMIT - RateLimiter::attempts(self::pickKey($member)));
    }

    public static function throwFrom(Member $sender, string $body): self
    {
        $bottle = static::query()->create([
            'sender_id' => $sender->getKey(),
            'body' => $body,
        ]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $recipient = $bottle->eligibleRecipients()->inRandomOrder()->first();

            if ($recipient === null || $bottle->claimFor($recipient)) {
                break;
            }
        }

        return $bottle->fresh();
    }

    /**
     * Take one bottle out of the sea for the member, or null when none is available.
     */
    public static function pick(Member $member): ?self
    {
        $excluded = self::strangerExclusions($member);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $bottle = static::query()
                ->inSea()
                ->where('sender_id', '!=', $member->getKey())
                ->whereNotIn('sender_id', $excluded)
                ->whereHas('sender', fn (Builder $query) => $query->where('is_active', true))
                ->where(fn (Builder $query) => $query
                    ->whereNull('released_recipient_ids')
                    ->orWhereJsonDoesntContain('released_recipient_ids', $member->getKey()))
                ->inRandomOrder()
                ->first();

            if ($bottle === null) {
                return null;
            }

            if ($bottle->claimFor($member)) {
                return $bottle->fresh();
            }
        }

        return null;
    }

    /**
     * @return Builder<Member>
     */
    public function eligibleRecipients(): Builder
    {
        $sender = $this->sender;
        $excluded = self::strangerExclusions($sender)
            ->push($sender->getKey())
            ->merge($this->released_recipient_ids ?? []);

        $saturated = static::query()
            ->select('recipient_id')
            ->whereNotNull('recipient_id')
            ->whereNull('opened_at')
            ->whereNull('removed_at')
            ->groupBy('recipient_id')
            ->havingRaw('count(*) >= ?', [self::HOLD_LIMIT]);

        return Member::query()
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->whereNotIn('id', $excluded)
            ->whereNotIn('id', $saturated);
    }

    /**
     * Friends, pending invitations and blocks in either direction.
     *
     * @return Collection<int, string>
     */
    private static function strangerExclusions(Member $member): Collection
    {
        $related = Friendship::query()
            ->involving($member)
            ->get(['member_low_id', 'member_high_id'])
            ->flatMap(fn (Friendship $friendship) => [$friendship->member_low_id, $friendship->member_high_id]);

        return $related
            ->merge($member->blockedPairMemberIds())
            ->reject(fn (string $id) => $id === $member->getKey())
            ->unique()
            ->values();
    }

    private function claimFor(Member $recipient): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->whereNull('recipient_id')
            ->whereNull('opened_at')
            ->whereNull('removed_at')
            ->update(['recipient_id' => $recipient->getKey(), 'assigned_at' => now()]) === 1;
    }

    /**
     * Return an unopened bottle to the sea. A stale model never overrides a concurrent open or removal.
     */
    public function release(): bool
    {
        return DB::transaction(function (): bool {
            $current = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($current === null
                || $current->recipient_id === null
                || $current->opened_at !== null
                || $current->removed_at !== null) {
                return false;
            }

            $released = $current->released_recipient_ids ?? [];
            $released[] = $current->recipient_id;

            $updated = static::query()
                ->whereKey($current->getKey())
                ->where('recipient_id', $current->recipient_id)
                ->whereNull('opened_at')
                ->whereNull('removed_at')
                ->update([
                    'recipient_id' => null,
                    'assigned_at' => null,
                    'released_recipient_ids' => json_encode(array_values(array_unique($released))),
                ]);

            $this->refresh();

            return $updated === 1;
        });
    }

    public function open(Member $by): void
    {
        if ($this->recipient_id !== $by->getKey() || $this->removed_at !== null || $this->opened_at !== null) {
            return;
        }

        static::query()
            ->whereKey($this->getKey())
            ->where('recipient_id', $by->getKey())
            ->whereNull('opened_at')
            ->whereNull('removed_at')
            ->update(['opened_at' => now()]);
    }

    public function replyFrom(Member $by, string $body): bool
    {
        if ($this->recipient_id !== $by->getKey() || $this->opened_at === null || $this->removed_at !== null) {
            return false;
        }

        return static::query()
            ->whereKey($this->getKey())
            ->whereNull('replied_at')
            ->whereNull('removed_at')
            ->update(['reply_body' => $body, 'replied_at' => now()]) === 1;
    }

    public function reportBy(Member $by, string $target, ?string $reason = null): bool
    {
        $allowed = match ($target) {
            self::TARGET_BOTTLE => $this->recipient_id === $by->getKey() && $this->removed_at === null,
            self::TARGET_REPLY => $this->sender_id === $by->getKey()
                && $this->replied_at !== null
                && $this->reply_removed_at === null,
            default => false,
        };

        if (! $allowed) {
            return false;
        }

        return DB::transaction(function () use ($by, $target, $reason): bool {
            $report = DriftBottleReport::query()->firstOrCreate(
                ['bottle_id' => $this->getKey(), 'reporter_id' => $by->getKey(), 'target' => $target],
                ['reason' => $reason !== null ? mb_substr($reason, 0, 200) : null],
            );

            if (! $report->wasRecentlyCreated) {
                return false;
            }

            $column = $target === self::TARGET_BOTTLE ? 'removed_at' : 'reply_removed_at';
            $this->forceFill([$column => now()])->save();

            return true;
        });
    }

    public function removeBottle(): void
    {
        $this->forceFill(['removed_at' => now()])->save();
    }

    public function restoreBottle(): void
    {
        $this->forceFill(['removed_at' => null])->save();
    }

    public function removeReply(): void
    {
        $this->forceFill(['reply_removed_at' => now()])->save();
    }

    public function restoreReply(): void
    {
        $this->forceFill(['reply_removed_at' => null])->save();
    }
}
