<?php

namespace App\Models;

use Database\Factories\MemberBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
