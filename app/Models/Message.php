<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sender_id', 'recipient_id', 'body', 'read_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const MAX_LENGTH = 1000;

    public const PAGE_SIZE = 50;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
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

    public function scopeBetween(Builder $query, Member $a, Member $b): Builder
    {
        return $query->where(function (Builder $query) use ($a, $b): void {
            $query->where(function (Builder $query) use ($a, $b): void {
                $query->where('sender_id', $a->getKey())->where('recipient_id', $b->getKey());
            })->orWhere(function (Builder $query) use ($a, $b): void {
                $query->where('sender_id', $b->getKey())->where('recipient_id', $a->getKey());
            });
        });
    }

    public static function sendFrom(Member $sender, Member $recipient, string $body): ?self
    {
        if (! $sender->canMessage($recipient)) {
            return null;
        }

        $message = static::query()->create([
            'sender_id' => $sender->getKey(),
            'recipient_id' => $recipient->getKey(),
            'body' => $body,
        ]);

        CarrotLedger::awardQuietly(
            $sender,
            'first_message',
            CarrotLedger::siteRewards()['first_message'] ?? 0,
            CarrotLedger::siteDedupeKey('first_message', $sender),
        );

        return $message;
    }

    public static function markRead(Member $reader, Member $other): int
    {
        return static::query()
            ->where('recipient_id', $reader->getKey())
            ->where('sender_id', $other->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @return Collection<int, Message>
     */
    public static function latestPage(Member $a, Member $b, ?int $beforeId = null): Collection
    {
        $query = static::query()
            ->between($a, $b)
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE);

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query->get()->reverse()->values();
    }
}
