<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_an_active_verified_member(): void
    {
        $member = Member::factory()->create();

        $this->assertTrue($member->is_active);
        $this->assertNotNull($member->email_verified_at);
        $this->assertTrue(Hash::check('password', $member->password));
    }

    public function test_password_is_hashed_and_hidden_from_serialization(): void
    {
        $member = Member::factory()->create(['password' => 'plain-secret']);

        $this->assertNotSame('plain-secret', $member->password);
        $this->assertArrayNotHasKey('password', $member->toArray());
        $this->assertArrayNotHasKey('remember_token', $member->toArray());
    }

    public function test_unverified_and_inactive_states(): void
    {
        $this->assertNull(Member::factory()->unverified()->create()->email_verified_at);
        $this->assertFalse(Member::factory()->inactive()->create()->is_active);
    }

    public function test_email_must_be_unique_among_members(): void
    {
        Member::factory()->create(['email' => 'same@example.com']);

        $this->expectException(UniqueConstraintViolationException::class);

        Member::factory()->create(['email' => 'same@example.com']);
    }

    public function test_member_email_may_match_an_admin_user_email(): void
    {
        User::factory()->create(['email' => 'shared@example.com']);

        $member = Member::factory()->create(['email' => 'shared@example.com']);

        $this->assertDatabaseHas('members', ['id' => $member->id, 'email' => 'shared@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'shared@example.com']);
    }

    public function test_organization_is_optional(): void
    {
        $member = Member::factory()->create(['organization' => null]);

        $this->assertNull($member->fresh()->organization);
    }
}
