<?php

namespace App\Models;

use Database\Factories\NpcCharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'member_id'])]
class NpcCharacter extends Model
{
    /** @use HasFactory<NpcCharacterFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return HasMany<DriftBottle, $this>
     */
    public function bottles(): HasMany
    {
        return $this->hasMany(DriftBottle::class);
    }
}
