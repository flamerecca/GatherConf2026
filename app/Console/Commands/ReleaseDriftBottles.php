<?php

namespace App\Console\Commands;

use App\Models\DriftBottle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('drift-bottles:release')]
#[Description('Return drift bottles that nobody opened within 24 hours to the sea')]
class ReleaseDriftBottles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $released = 0;

        DriftBottle::query()
            ->whereNotNull('sender_id')
            ->whereNull('npc_character_id')
            ->whereNotNull('recipient_id')
            ->whereNull('opened_at')
            ->whereNull('removed_at')
            ->where('assigned_at', '<', now()->subHours(DriftBottle::RELEASE_AFTER_HOURS))
            ->each(function (DriftBottle $bottle) use (&$released): void {
                if ($bottle->release()) {
                    $released++;
                }
            });

        $this->info("Released {$released} drift bottles.");

        return self::SUCCESS;
    }
}
