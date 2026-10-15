<?php

namespace App\Models;

use Database\Factories\DriftBottleReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function review(string $outcome, ?int $by): void
    {
        $this->forceFill(['outcome' => $outcome, 'reviewed_by' => $by, 'reviewed_at' => now()])->save();

        if ($outcome === self::OUTCOME_DISMISSED) {
            $this->target === DriftBottle::TARGET_BOTTLE
                ? $this->bottle->restoreBottle()
                : $this->bottle->restoreReply();
        }
    }
}
