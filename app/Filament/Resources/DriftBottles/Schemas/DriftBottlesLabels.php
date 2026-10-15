<?php

namespace App\Filament\Resources\DriftBottles\Schemas;

use App\Models\DriftBottle;

class DriftBottlesLabels
{
    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            DriftBottle::STATUS_IN_SEA => '漂流中',
            DriftBottle::STATUS_DELIVERED => '尚未開啟',
            DriftBottle::STATUS_OPENED => '已開啟',
            DriftBottle::STATUS_REPLIED => '已回覆',
            DriftBottle::STATUS_REMOVED => '已下架',
        ];
    }

    public static function status(string $status): string
    {
        return self::statuses()[$status] ?? $status;
    }
}
