<?php

namespace App\Models;

use Database\Factories\MemberLoginLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'token_hash', 'expires_at', 'used_at', 'revoked_at'])]
class MemberLoginLink extends Model
{
    /** @use HasFactory<MemberLoginLinkFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_USABLE = 'usable';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

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
        if (! $this->isUsable()) {
            return false;
        }

        $this->forceFill(['revoked_at' => now()])->save();

        return true;
    }
}
