<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordlessDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_password_column_is_nullable(): void
    {
        $column = collect(Schema::getColumns('members'))->firstWhere('name', 'password');

        $this->assertTrue($column['nullable']);
    }

    public function test_has_password_reflects_whether_a_hash_is_stored(): void
    {
        $this->assertTrue(Member::factory()->create()->hasPassword());
        $this->assertFalse(Member::factory()->passwordless()->create()->hasPassword());
    }

    public function test_a_passwordless_member_cannot_log_in_with_any_password(): void
    {
        Member::factory()->passwordless()->create(['email' => 'new@example.com']);

        foreach (['x', 'password', 'null', '0'] as $guess) {
            $this->post('/login', ['email' => 'new@example.com', 'password' => $guess])
                ->assertSessionHasErrors(['email' => __('member.errors.failed')]);
        }

        $this->assertGuest('member');
    }

    public function test_the_failure_message_is_the_same_for_passwordless_wrong_password_and_unknown(): void
    {
        Member::factory()->passwordless()->create(['email' => 'none@example.com']);
        Member::factory()->create(['email' => 'has@example.com']);

        $messages = collect([
            ['none@example.com', 'whatever'],
            ['has@example.com', 'wrong-password'],
            ['ghost@example.com', 'whatever'],
        ])->map(function (array $credentials): string {
            $this->post('/login', ['email' => $credentials[0], 'password' => $credentials[1]])->assertSessionHasErrors('email');

            return (string) session('errors')->first('email');
        });

        $this->assertCount(1, $messages->unique());
    }

    public function test_a_deactivated_passwordless_member_gets_the_generic_failure(): void
    {
        Member::factory()->passwordless()->inactive()->create(['email' => 'off@example.com']);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'anything'])
            ->assertSessionHasErrors(['email' => __('member.errors.failed')]);
    }

    public function test_the_admin_user_password_column_is_untouched(): void
    {
        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'password');

        $this->assertFalse($column['nullable']);
    }
}
