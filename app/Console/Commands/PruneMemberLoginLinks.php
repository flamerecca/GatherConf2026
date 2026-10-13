<?php

namespace App\Console\Commands;

use App\Models\MemberLoginLink;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('member-login-links:prune')]
#[Description('Delete login link records that ended more than 30 days ago')]
class PruneMemberLoginLinks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays(30);

        $deleted = MemberLoginLink::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where('used_at', '<', $cutoff)
                    ->orWhere('revoked_at', '<', $cutoff)
                    ->orWhere('expires_at', '<', $cutoff);
            })
            ->delete();

        $this->info("Deleted {$deleted} login link records.");

        return self::SUCCESS;
    }
}
