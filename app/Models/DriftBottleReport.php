<?php

namespace App\Models;

use Database\Factories\DriftBottleReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['bottle_id', 'reporter_id', 'target', 'reason'])]
class DriftBottleReport extends Model
{
    /** @use HasFactory<DriftBottleReportFactory> */
    use HasFactory, HasUuids;

    public const OUTCOME_VIOLATION = 'violation';

    public const OUTCOME_DISMISSED = 'dismissed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function bottle(): BelongsTo
    {
        return $this->belongsTo(DriftBottle::class, 'bottle_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reporter_id');
    }

    public function isPending(): bool
    {
        return $this->reviewed_at === null;
    }

    /**
     * Record the outcome. A dismissal only restores the content when no other report still stands.
     */
    public function review(string $outcome, ?int $by): bool
    {
        return DB::transaction(function () use ($outcome, $by): bool {
            $current = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($current === null || $current->reviewed_at !== null) {
                return false;
            }

            $this->forceFill(['outcome' => $outcome, 'reviewed_by' => $by, 'reviewed_at' => now()])->save();

            if ($outcome === self::OUTCOME_DISMISSED && ! $this->otherReportsStand()) {
                $this->target === DriftBottle::TARGET_BOTTLE
                    ? $this->bottle->restoreBottle()
                    : $this->bottle->restoreReply();
            }

            return true;
        });
    }

    private function otherReportsStand(): bool
    {
        return static::query()
            ->where('bottle_id', $this->bottle_id)
            ->where('target', $this->target)
            ->whereKeyNot($this->getKey())
            ->where(fn ($query) => $query
                ->whereNull('reviewed_at')
                ->orWhere('outcome', self::OUTCOME_VIOLATION))
            ->exists();
    }
}
