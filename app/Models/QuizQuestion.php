<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['axis', 'positive_pole', 'text', 'order', 'is_active'])]
class QuizQuestion extends Model
{
    use SoftDeletes;

    public const PER_QUIZ = 12;

    public const SESSION_KEY = 'quiz.question_ids';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Pick the questions for one quiz from the active pool, spread over the axes as evenly as the pool allows,
     * and keep them in the order the editors set.
     *
     * @return Collection<int, QuizQuestion>
     */
    public static function draw(int $count = self::PER_QUIZ): Collection
    {
        $pool = static::query()->where('is_active', true)->orderBy('order')->orderBy('id')->get();

        if ($pool->count() <= $count) {
            return $pool;
        }

        $groups = $pool->groupBy('axis')->shuffle();
        $base = intdiv($count, $groups->count());
        $extra = $count % $groups->count();

        $picked = $groups->values()->flatMap(
            fn (Collection $group, int $index): Collection => $group->shuffle()->take($base + ($index < $extra ? 1 : 0)),
        );

        $picked = $picked->merge($pool->diff($picked)->shuffle()->take($count - $picked->count()));

        return $picked->sortBy([['order', 'asc'], ['id', 'asc']])->values();
    }
}
