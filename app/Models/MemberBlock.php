<?php

namespace App\Models;

use Database\Factories\MemberBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['blocker_id', 'blocked_id'])]
class MemberBlock extends Model
{
    /** @use HasFactory<MemberBlockFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'blocked_id');
    }

    public static function block(Member $blocker, Member $blocked): void
    {
        DB::transaction(function () use ($blocker, $blocked): void {
            static::query()->firstOrCreate([
                'blocker_id' => $blocker->getKey(),
                'blocked_id' => $blocked->getKey(),
            ]);

            Friendship::between($blocker, $blocked)?->delete();
        });
    }

    public static function unblock(Member $blocker, Member $blocked): void
    {
        static::query()
            ->where('blocker_id', $blocker->getKey())
            ->where('blocked_id', $blocked->getKey())
            ->delete();
    }
}
