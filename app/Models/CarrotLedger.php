<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class CarrotLedger
{
    public const REASON_MANUAL = 'manual';

    public const REASON_TASK = 'task';

    /**
     * The largest balance the unsigned members.carrots column can hold.
     */
    public const MAX_BALANCE = 4294967295;

    /**
     * @return array<string, int>
     */
    public static function siteRewards(): array
    {
        return array_map('intval', config('carrots.site', []));
    }

    public static function siteDedupeKey(string $behaviour, Member $member): string
    {
        return $behaviour.':'.$member->getKey();
    }

    /**
     * Grant carrots once per dedupe key. Returns false when this key was already granted.
     */
    public static function award(Member $member, string $reason, int $amount, string $dedupeKey, ?CarrotTask $task = null): bool
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('An award must be a positive amount.');
        }

        try {
            return self::record($member, $amount, $reason, $dedupeKey, $task?->getKey(), null, null) !== null;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Same as award() but never lets a failure escape, for use inside other behaviours.
     * A zero reward means the behaviour is switched off, so it is skipped without a report.
     * Call it after the surrounding transaction has committed, not inside one.
     */
    public static function awardQuietly(Member $member, string $reason, int $amount, string $dedupeKey, ?CarrotTask $task = null): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            return self::award($member, $reason, $amount, $dedupeKey, $task);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Admin adjustment. Every call is its own record and the balance may not go below zero.
     */
    public static function adjust(Member $member, int $amount, string $note, ?int $createdBy = null): CarrotTransaction
    {
        if ($amount === 0) {
            throw new InvalidArgumentException('An adjustment cannot be zero.');
        }

        return self::record($member, $amount, self::REASON_MANUAL, 'manual:'.Str::uuid7(), null, $note, $createdBy);
    }

    public static function balance(Member $member): int
    {
        return (int) CarrotTransaction::query()->where('member_id', $member->getKey())->sum('amount');
    }

    private static function record(
        Member $member,
        int $amount,
        string $reason,
        string $dedupeKey,
        ?string $taskId,
        ?string $note,
        ?int $createdBy,
    ): ?CarrotTransaction {
        return DB::transaction(function () use ($member, $amount, $reason, $dedupeKey, $taskId, $note, $createdBy): ?CarrotTransaction {
            $locked = Member::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();

            if (CarrotTransaction::query()->where('dedupe_key', $dedupeKey)->exists()) {
                return null;
            }

            $balance = $locked->carrots + $amount;

            if ($balance < 0) {
                throw new DomainException('Carrot balance cannot go below zero.');
            }

            if ($balance > self::MAX_BALANCE) {
                throw new DomainException('Carrot balance is above the supported limit.');
            }

            $transaction = CarrotTransaction::query()->create([
                'member_id' => $locked->getKey(),
                'amount' => $amount,
                'reason' => $reason,
                'note' => $note,
                'task_id' => $taskId,
                'created_by' => $createdBy,
                'dedupe_key' => $dedupeKey,
            ]);

            Member::query()->whereKey($locked->getKey())->update(['carrots' => $balance]);

            $member->setAttribute('carrots', $balance)->syncOriginalAttribute('carrots');

            return $transaction;
        });
    }
}
