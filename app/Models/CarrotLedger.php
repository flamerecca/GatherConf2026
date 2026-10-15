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

    public const REASON_TASK_REVOKED = 'task_revoked';

    /**
     * The balance range the signed members.carrots column can hold. A balance may be negative.
     */
    public const MAX_BALANCE = 2147483647;

    public const MIN_BALANCE = -2147483648;

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
     * Admin adjustment. Every call is its own record and the balance may go below zero.
     */
    public static function adjust(Member $member, int $amount, string $note, ?int $createdBy = null): CarrotTransaction
    {
        if ($amount === 0) {
            throw new InvalidArgumentException('An adjustment cannot be zero.');
        }

        return self::record($member, $amount, self::REASON_MANUAL, 'manual:'.Str::uuid7(), null, $note, $createdBy);
    }

    /**
     * Take back a task reward by adding a reversing entry, the original entry is left untouched.
     * Returns null when the task was never completed or was already revoked.
     * The member cannot earn the same task again afterwards.
     *
     * @throws DomainException when the resulting balance is outside the supported range.
     */
    public static function revokeTask(Member $member, CarrotTask $task, ?int $createdBy = null): ?CarrotTransaction
    {
        return self::revoke($member, $task->dedupeKeyFor($member), $task->getKey(), $createdBy);
    }

    /**
     * Same as revokeTask() for a one-off site behaviour, found by its behaviour code.
     *
     * @throws DomainException when the resulting balance is outside the supported range.
     */
    public static function revokeSiteReward(Member $member, string $behaviour, ?int $createdBy = null): ?CarrotTransaction
    {
        return self::revoke($member, self::siteDedupeKey($behaviour, $member), null, $createdBy);
    }

    public static function isRevoked(Member $member, string $dedupeKey): bool
    {
        return CarrotTransaction::query()->where('dedupe_key', 'revoke:'.$dedupeKey)->exists();
    }

    private static function revoke(Member $member, string $dedupeKey, ?string $taskId, ?int $createdBy): ?CarrotTransaction
    {
        $original = CarrotTransaction::query()->where('dedupe_key', $dedupeKey)->first();

        if ($original === null) {
            return null;
        }

        return self::record(
            $member,
            -$original->amount,
            self::REASON_TASK_REVOKED,
            'revoke:'.$dedupeKey,
            $taskId,
            null,
            $createdBy,
        );
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

            if ($balance < self::MIN_BALANCE || $balance > self::MAX_BALANCE) {
                throw new DomainException('Carrot balance is outside the supported range.');
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
