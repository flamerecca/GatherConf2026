<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberLoginLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_link_is_usable(): void
    {
        $link = MemberLoginLink::factory()->create();

        $this->assertSame(MemberLoginLink::STATUS_USABLE, $link->status());
        $this->assertTrue($link->isUsable());
    }

    public function test_status_for_used_expired_and_revoked_links(): void
    {
        $this->assertSame(MemberLoginLink::STATUS_USED, MemberLoginLink::factory()->used()->create()->status());
        $this->assertSame(MemberLoginLink::STATUS_EXPIRED, MemberLoginLink::factory()->expired()->create()->status());
        $this->assertSame(MemberLoginLink::STATUS_REVOKED, MemberLoginLink::factory()->revoked()->create()->status());
    }

    public function test_a_link_expiring_exactly_now_is_expired(): void
    {
        $this->freezeTime();

        $link = MemberLoginLink::factory()->create(['expires_at' => now()]);

        $this->assertSame(MemberLoginLink::STATUS_EXPIRED, $link->status());
    }

    public function test_used_takes_precedence_over_expired(): void
    {
        $link = MemberLoginLink::factory()->used()->create(['expires_at' => now()->subDay()]);

        $this->assertSame(MemberLoginLink::STATUS_USED, $link->status());
    }

    public function test_revoke_marks_a_usable_link(): void
    {
        $link = MemberLoginLink::factory()->create();

        $this->assertTrue($link->revoke());
        $this->assertNotNull($link->fresh()->revoked_at);
        $this->assertSame(MemberLoginLink::STATUS_REVOKED, $link->fresh()->status());
    }

    public function test_revoke_does_nothing_for_used_or_expired_links(): void
    {
        $used = MemberLoginLink::factory()->used()->create();
        $expired = MemberLoginLink::factory()->expired()->create();

        $this->assertFalse($used->revoke());
        $this->assertFalse($expired->revoke());
        $this->assertNull($used->fresh()->revoked_at);
        $this->assertNull($expired->fresh()->revoked_at);
    }

    public function test_deleting_a_member_deletes_its_links(): void
    {
        $member = Member::factory()->create();
        MemberLoginLink::factory()->count(2)->for($member)->create();

        $member->delete();

        $this->assertDatabaseCount('member_login_links', 0);
    }

    public function test_member_has_login_links_relation(): void
    {
        $member = Member::factory()->create();
        MemberLoginLink::factory()->count(3)->for($member)->create();

        $this->assertCount(3, $member->loginLinks);
    }
}
