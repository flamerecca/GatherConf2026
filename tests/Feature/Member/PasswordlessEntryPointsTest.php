<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordlessEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_passwordless_member_sees_the_set_password_prompt(): void
    {
        $member = Member::factory()->passwordless()->create();

        $this->actingAs($member, 'member')->get('/member')
            ->assertSee('data-set-password-prompt', false)
            ->assertSee(route('member.password.edit'), false);

        $this->actingAs($member, 'member')->get('/en/member')
            ->assertSee(route('member.password.edit.en'), false);
    }

    public function test_a_member_with_a_password_gets_a_change_link_but_no_prompt(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')->get('/member')
            ->assertDontSee('data-set-password-prompt', false)
            ->assertSee(route('member.password.edit'), false);
    }

    public function test_the_admin_sees_whether_a_password_is_set_but_never_the_hash(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
        $with = Member::factory()->create();
        $without = Member::factory()->passwordless()->create();

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$with, $without])
            ->assertDontSee($with->password);

        Livewire::test(ViewMember::class, ['record' => $with->getKey()])
            ->assertOk()
            ->assertSee('已設定密碼')
            ->assertDontSee($with->password);
    }
}
