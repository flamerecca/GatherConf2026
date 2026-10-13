<?php

namespace Tests\Feature\Member;

use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PruneMemberLoginLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_old_used_revoked_and_expired_links_only(): void
    {
        $oldUsed = MemberLoginLink::factory()->create(['used_at' => now()->subDays(31)]);
        $oldRevoked = MemberLoginLink::factory()->create(['revoked_at' => now()->subDays(31)]);
        $oldExpired = MemberLoginLink::factory()->create(['expires_at' => now()->subDays(31)]);

        $recentUsed = MemberLoginLink::factory()->create(['used_at' => now()->subDays(29)]);
        $recentExpired = MemberLoginLink::factory()->create(['expires_at' => now()->subDays(29)]);
        $usable = MemberLoginLink::factory()->create();

        $this->artisan('member-login-links:prune')->assertSuccessful();

        $this->assertModelMissing($oldUsed);
        $this->assertModelMissing($oldRevoked);
        $this->assertModelMissing($oldExpired);
        $this->assertModelExists($recentUsed);
        $this->assertModelExists($recentExpired);
        $this->assertModelExists($usable);
    }

    public function test_it_runs_daily(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('member-login-links:prune', Artisan::output());
    }
}
