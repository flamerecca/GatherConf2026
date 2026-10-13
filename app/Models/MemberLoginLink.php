<?php

namespace App\Models;

use Database\Factories\MemberLoginLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['member_id', 'token_hash', 'expires_at', 'used_at', 'revoked_at'])]
#[Hidden(['token_hash'])]
class MemberLoginLink extends Model
{
    /** @use HasFactory<MemberLoginLinkFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_USABLE = 'usable';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const VALID_MINUTES = 15;

    public const HOURLY_LIMIT = 5;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function status(): string
    {
        return match (true) {
            $this->used_at !== null => self::STATUS_USED,
            $this->revoked_at !== null => self::STATUS_REVOKED,
            $this->expires_at->lte(now()) => self::STATUS_EXPIRED,
            default => self::STATUS_USABLE,
        };
    }

    public function isUsable(): bool
    {
        return $this->status() === self::STATUS_USABLE;
    }

    public function revoke(): bool
    {
        $revoked = static::query()
            ->whereKey($this->getKey())
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['revoked_at' => now()]);

        if ($revoked !== 1) {
            return false;
        }

        $this->refresh();

        return true;
    }

    public static function issueFor(Member $member): string
    {
        $token = Str::random(64);

        DB::transaction(function () use ($member, $token): void {
            static::query()
                ->where('member_id', $member->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->update(['revoked_at' => now()]);

            static::query()->create([
                'member_id' => $member->getKey(),
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(self::VALID_MINUTES),
            ]);
        });

        return $token;
    }

    public static function consume(string $token): ?Member
    {
        $link = static::query()
            ->where('token_hash', hash('sha256', $token))
            ->with('member')
            ->first();

        if ($link === null || $link->member === null || ! $link->member->is_active) {
            return null;
        }

        $claimed = static::query()
            ->whereKey($link->getKey())
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        return $claimed === 1 ? $link->member : null;
    }

    public static function throttleKey(string $email): string
    {
        return 'member-login-link:'.Str::lower(trim($email));
    }
}
