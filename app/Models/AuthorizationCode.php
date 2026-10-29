<?php

namespace App\Models;

use Database\Factories\AuthorizationCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'code', 'reward', 'max_uses', 'is_active', 'starts_at', 'ends_at'])]
class AuthorizationCode extends Model
{
    /** @use HasFactory<AuthorizationCodeFactory> */
    use HasFactory, HasUuids;

    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const CODE_LENGTH = 8;

    public const STATE_EARNED = 'earned';

    public const STATE_DONE = 'done';

    public const STATE_CLOSED = 'closed';

    public const STATE_EXHAUSTED = 'exhausted';

    protected static function booted(): void
    {
        static::saving(function (AuthorizationCode $code): void {
            if (empty($code->code)) {
                $code->code = self::newCode();
            }

            $code->code = self::normalize($code->code);
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
            'reward' => 'integer',
            'max_uses' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Upper case and drop everything but letters and digits, so members can type spaces or dashes.
     */
    public static function normalize(string $input): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
    }

    public static function randomCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    public static function newCode(): string
    {
        do {
            $code = self::randomCode();
        } while (static::query()->where('code', $code)->exists() || CarrotTask::query()->where('short_code', $code)->exists());

        return $code;
    }

    /**
     * @return HasMany<CarrotTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CarrotTransaction::class, 'authorization_code_id');
    }

    public function usesCount(): int
    {
        return $this->transactions()->where('reason', CarrotLedger::REASON_CODE)->count();
    }

    public function isOpen(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /**
     * @param  Builder<AuthorizationCode>  $query
     * @return Builder<AuthorizationCode>
     */
    public function scopeMatching(Builder $query, string $input): Builder
    {
        return $query->where('code', self::normalize($input));
    }

    public function dedupeKeyFor(Member $member): string
    {
        return 'code:'.$this->getKey().':'.$member->getKey();
    }

    /**
     * Exchange the code for carrots. One member can use one code once, and a code can have a limit on total uses.
     *
     * @return self::STATE_*
     */
    public function redeem(Member $member): string
    {
        return DB::transaction(function () use ($member): string {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                return self::STATE_CLOSED;
            }

            if (CarrotTransaction::query()->where('dedupe_key', $locked->dedupeKeyFor($member))->exists()) {
                return self::STATE_DONE;
            }

            if ($locked->max_uses !== null && $locked->usesCount() >= $locked->max_uses) {
                return self::STATE_EXHAUSTED;
            }

            $earned = CarrotLedger::award($member, CarrotLedger::REASON_CODE, $locked->reward, $locked->dedupeKeyFor($member), null, $locked);

            return $earned ? self::STATE_EARNED : self::STATE_DONE;
        });
    }
}
