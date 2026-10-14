<?php

namespace App\Models;

use App\Notifications\ResetMemberPasswordNotification;
use App\Notifications\VerifyMemberEmailNotification;
use Database\Factories\MemberFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'organization', 'is_active'])]
#[Hidden(['password', 'remember_token', 'friend_code'])]
class Member extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (Member $member): void {
            if (empty($member->friend_code)) {
                $member->friend_code = self::newFriendCode();
            }
        });

        static::updated(function (Member $member): void {
            $rewards = CarrotLedger::siteRewards();

            if ($member->wasChanged('email_verified_at') && $member->email_verified_at !== null) {
                CarrotLedger::awardQuietly(
                    $member,
                    'verify_email',
                    $rewards['verify_email'] ?? 0,
                    CarrotLedger::siteDedupeKey('verify_email', $member),
                );
            }

            $hadPassword = ! in_array($member->getOriginal('password'), [null, ''], true);

            if ($member->wasChanged('password') && ! $hadPassword && $member->hasPassword()) {
                CarrotLedger::awardQuietly(
                    $member,
                    'set_password',
                    $rewards['set_password'] ?? 0,
                    CarrotLedger::siteDedupeKey('set_password', $member),
                );
            }
        });
    }

    public static function newFriendCode(): string
    {
        return Str::lower(Str::random(32));
    }

    public function regenerateFriendCode(): void
    {
        $this->forceFill(['friend_code' => self::newFriendCode()])->save();
    }

    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    /**
     * Remove every credential that may predate proof of mailbox ownership.
     */
    public function discardCredentials(): void
    {
        $this->forceFill(['password' => null])->setRememberToken(Str::random(60));
        $this->save();

        $this->purgeSessions();
    }

    /**
     * End every stored session of this member. Only database sessions can be listed.
     */
    public function purgeSessions(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', (string) $this->getKey())
            ->delete();
    }

    public function friendUrl(?string $locale = null): string
    {
        $name = ($locale ?? app()->getLocale()) === 'en' ? 'member.friends.add.en' : 'member.friends.add';

        return route($name, ['code' => $this->friend_code]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'carrots' => 'integer',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyMemberEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetMemberPasswordNotification($token));
    }

    public function carrotTransactions(): HasMany
    {
        return $this->hasMany(CarrotTransaction::class);
    }

    public function hasBlocked(Member $other): bool
    {
        return MemberBlock::query()
            ->where('blocker_id', $this->getKey())
            ->where('blocked_id', $other->getKey())
            ->exists();
    }

    /**
     * Ids of members this member has blocked or who have blocked this member.
     *
     * @return Collection<int, string>
     */
    public function blockedPairMemberIds(): Collection
    {
        return MemberBlock::query()
            ->where('blocker_id', $this->getKey())
            ->orWhere('blocked_id', $this->getKey())
            ->get(['blocker_id', 'blocked_id'])
            ->flatMap(fn (MemberBlock $block) => [$block->blocker_id, $block->blocked_id])
            ->reject(fn (string $id) => $id === $this->getKey())
            ->unique()
            ->values();
    }

    public function isBlockedBy(Member $other): bool
    {
        return $other->hasBlocked($this);
    }

    public function isFriendWith(Member $other): bool
    {
        return Friendship::between($this, $other)?->isAccepted() ?? false;
    }

    public function canBlock(Member $target): bool
    {
        if ($this->is($target)) {
            return false;
        }

        $friendship = Friendship::between($this, $target);

        if ($friendship === null) {
            return false;
        }

        return $friendship->isAccepted() || $friendship->requester_id === $target->getKey();
    }

    public function canMessage(Member $other): bool
    {
        return ! $this->is($other)
            && $this->is_active
            && $other->is_active
            && $this->isFriendWith($other)
            && ! $this->hasBlocked($other)
            && ! $this->isBlockedBy($other);
    }

    /**
     * Ids of active, unblocked members who are accepted friends.
     *
     * @return Collection<int, string>
     */
    public function messageableFriendIds(): Collection
    {
        $blocked = $this->blockedPairMemberIds();

        return Friendship::query()
            ->accepted()
            ->involving($this)
            ->with(['low', 'high'])
            ->get()
            ->map(fn (Friendship $friendship) => $friendship->otherMember($this))
            ->filter(fn (Member $other) => $other->is_active && ! $blocked->contains($other->getKey()))
            ->map(fn (Member $other) => $other->getKey())
            ->values();
    }

    public function unreadMessageCount(): int
    {
        return Message::query()
            ->where('recipient_id', $this->getKey())
            ->whereNull('read_at')
            ->whereIn('sender_id', $this->messageableFriendIds())
            ->count();
    }

    public function unreadMessageCountFrom(Member $other): int
    {
        if (! $this->messageableFriendIds()->contains($other->getKey())) {
            return 0;
        }

        return Message::query()
            ->where('recipient_id', $this->getKey())
            ->where('sender_id', $other->getKey())
            ->whereNull('read_at')
            ->count();
    }

    public function loginLinks(): HasMany
    {
        return $this->hasMany(MemberLoginLink::class);
    }
}
