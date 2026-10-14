<?php

namespace App\Models;

use Database\Factories\CarrotTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'reward', 'is_active', 'starts_at', 'ends_at'])]
class CarrotTask extends Model
{
    /** @use HasFactory<CarrotTaskFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::creating(function (CarrotTask $task): void {
            if (empty($task->code)) {
                $task->code = Str::lower(Str::random(32));
            }
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
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CarrotTransaction::class, 'task_id');
    }

    public function isOpen(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /**
     * @param  Builder<CarrotTask>  $query
     * @return Builder<CarrotTask>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function dedupeKeyFor(Member $member): string
    {
        return 'task:'.$this->getKey().':'.$member->getKey();
    }
}
