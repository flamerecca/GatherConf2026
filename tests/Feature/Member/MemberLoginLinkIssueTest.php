<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberLoginLinkIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_for_returns_plain_token_and_stores_only_its_hash(): void
    {
        $member = Member::factory()->create();

        $token = MemberLoginLink::issueFor($member);

        $this->assertSame(64, strlen($token));
        $this->assertDatabaseHas('member_login_links', [
            'member_id' => $member->id,
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('member_login_links', ['token_hash' => $token]);
    }

    public function test_issued_link_expires_in_fifteen_minutes(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();

        MemberLoginLink::issueFor($member);

        $link = $member->loginLinks()->first();
        $this->assertSame(now()->addMinutes(15)->toDateTimeString(), $link->expires_at->toDateTimeString());
    }

    public function test_issuing_a_new_link_revokes_previous_usable_links(): void
    {
        $member = Member::factory()->create();
        $first = MemberLoginLink::issueFor($member);

        $second = MemberLoginLink::issueFor($member);

        $this->assertNull(MemberLoginLink::consume($first));
        $this->assertTrue(MemberLoginLink::consume($second)->is($member));
    }

    public function test_consume_marks_link_used_and_cannot_be_replayed(): void
    {
        $member = Member::factory()->create();
        $token = MemberLoginLink::issueFor($member);

        $this->assertTrue(MemberLoginLink::consume($token)->is($member));
        $this->assertNull(MemberLoginLink::consume($token));
        $this->assertNotNull($member->loginLinks()->first()->used_at);
    }

    public function test_consume_rejects_unknown_expired_revoked_and_inactive(): void
    {
        $this->assertNull(MemberLoginLink::consume('does-not-exist'));

        $member = Member::factory()->create();
        $expiredToken = MemberLoginLink::issueFor($member);
        $member->loginLinks()->update(['expires_at' => now()->subMinute()]);
        $this->assertNull(MemberLoginLink::consume($expiredToken));

        $revoked = Member::factory()->create();
        $revokedToken = MemberLoginLink::issueFor($revoked);
        $revoked->loginLinks()->first()->revoke();
        $this->assertNull(MemberLoginLink::consume($revokedToken));

        $inactive = Member::factory()->inactive()->create();
        $inactiveToken = MemberLoginLink::issueFor($inactive);
        $this->assertNull(MemberLoginLink::consume($inactiveToken));
    }

    public function test_a_failed_consume_leaves_an_unusable_link_untouched(): void
    {
        $member = Member::factory()->inactive()->create();
        $token = MemberLoginLink::issueFor($member);

        MemberLoginLink::consume($token);

        $this->assertNull($member->loginLinks()->first()->used_at);
    }

    public function test_revoke_cannot_override_a_link_used_in_the_meantime(): void
    {
        $member = Member::factory()->create();
        MemberLoginLink::issueFor($member);
        $stale = $member->loginLinks()->first();

        $member->loginLinks()->update(['used_at' => now()]);

        $this->assertFalse($stale->revoke());
        $this->assertNull($stale->fresh()->revoked_at);
    }

    public function test_token_hash_is_hidden_from_serialization(): void
    {
        $link = MemberLoginLink::factory()->create();

        $this->assertArrayNotHasKey('token_hash', $link->toArray());
    }

    public function test_throttle_key_is_case_and_whitespace_insensitive(): void
    {
        $this->assertSame(
            MemberLoginLink::throttleKey('alice@example.com'),
            MemberLoginLink::throttleKey('  Alice@Example.com '),
        );
    }
}
