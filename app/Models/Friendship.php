<?php

namespace App\Models;

use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_low_id', 'member_high_id', 'requester_id', 'status', 'responded_at'])]
class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const INVITE_INVITED = 'invited';

    public const INVITE_ACCEPTED = 'accepted';

    public const INVITE_ALREADY_FRIENDS = 'already_friends';

    public const INVITE_ALREADY_INVITED = 'already_invited';

    public const INVITE_BLOCKED = 'blocked';

    public const INVITE_SELF = 'self';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function low(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_low_id');
    }

    public function high(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_high_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requester_id');
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function orderedIds(string $a, string $b): array
    {
        return strcmp($a, $b) < 0 ? [$a, $b] : [$b, $a];
    }

    public static function between(Member $a, Member $b): ?self
    {
        [$low, $high] = self::orderedIds($a->getKey(), $b->getKey());

        return static::query()
            ->where('member_low_id', $low)
            ->where('member_high_id', $high)
            ->first();
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInvolving(Builder $query, Member $member): Builder
    {
        return $query->where(function (Builder $query) use ($member): void {
            $query->where('member_low_id', $member->getKey())
                ->orWhere('member_high_id', $member->getKey());
        });
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isParticipant(Member $member): bool
    {
        return in_array($member->getKey(), [$this->member_low_id, $this->member_high_id], true);
    }

    public function otherMember(Member $member): Member
    {
        return $this->member_low_id === $member->getKey() ? $this->high : $this->low;
    }

    public static function invite(Member $from, Member $to): string
    {
        if ($from->is($to)) {
            return self::INVITE_SELF;
        }

        if (! $to->is_active || $from->hasBlocked($to) || $from->isBlockedBy($to)) {
            return self::INVITE_BLOCKED;
        }

        [$low, $high] = self::orderedIds($from->getKey(), $to->getKey());

        $friendship = static::query()->createOrFirst(
            ['member_low_id' => $low, 'member_high_id' => $high],
            ['requester_id' => $from->getKey(), 'status' => self::STATUS_PENDING],
        );

        if ($friendship->wasRecentlyCreated) {
            if ($from->hasBlocked($to) || $from->isBlockedBy($to)) {
                $friendship->delete();

                return self::INVITE_BLOCKED;
            }

            return self::INVITE_INVITED;
        }

        if ($friendship->isAccepted()) {
            return self::INVITE_ALREADY_FRIENDS;
        }

        if ($friendship->requester_id === $from->getKey()) {
            return self::INVITE_ALREADY_INVITED;
        }

        $friendship->acceptBy($from);

        return self::INVITE_ACCEPTED;
    }

    public function acceptBy(Member $member): bool
    {
        if (! $this->isParticipant($member) || $this->requester_id === $member->getKey()) {
            return false;
        }

        $other = $this->otherMember($member);

        if (! $other->is_active || $member->hasBlocked($other) || $member->isBlockedBy($other)) {
            return false;
        }

        $accepted = static::query()
            ->whereKey($this->getKey())
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_ACCEPTED, 'responded_at' => now()]);

        if ($accepted !== 1) {
            return false;
        }

        $this->refresh();

        foreach ([$member, $other] as $participant) {
            CarrotLedger::awardQuietly(
                $participant,
                'first_friend',
                CarrotLedger::siteRewards()['first_friend'] ?? 0,
                CarrotLedger::siteDedupeKey('first_friend', $participant),
            );
        }

        return true;
    }

    public function removeBy(Member $member): bool
    {
        if (! $this->isParticipant($member)) {
            return false;
        }

        return (bool) $this->delete();
    }
}
