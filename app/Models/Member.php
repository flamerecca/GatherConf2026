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

    public function loginLinks(): HasMany
    {
        return $this->hasMany(MemberLoginLink::class);
    }
}
