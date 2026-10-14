<?php

namespace App\Models;

use App\Notifications\ResetMemberPasswordNotification;
use App\Notifications\VerifyMemberEmailNotification;
use Database\Factories\MemberFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'organization', 'is_active'])]
#[Hidden(['password', 'remember_token', 'friend_code'])]
class Member extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (Member $member): void {
            if (empty($member->friend_code)) {
                $member->friend_code = self::newFriendCode();
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
     * @return Collection<int, int>
     */
    public function blockedPairMemberIds(): Collection
    {
        return MemberBlock::query()
            ->where('blocker_id', $this->getKey())
            ->orWhere('blocked_id', $this->getKey())
            ->get(['blocker_id', 'blocked_id'])
            ->flatMap(fn (MemberBlock $block) => [$block->blocker_id, $block->blocked_id])
            ->reject(fn (int $id) => $id === $this->getKey())
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

    public function loginLinks(): HasMany
    {
        return $this->hasMany(MemberLoginLink::class);
    }
}
