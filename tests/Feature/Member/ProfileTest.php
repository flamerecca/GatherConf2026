<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_form_shows_the_current_nickname_and_is_not_indexed(): void
    {
        $member = Member::factory()->create(['name' => '小白兔']);

        $this->actingAs($member, 'member')->get('/member/profile')
            ->assertOk()
            ->assertSee('value="小白兔"', false)
            ->assertSee('name="name"', false)
            ->assertSee('noindex', false);
    }

    public function test_a_member_can_change_their_nickname(): void
    {
        $member = Member::factory()->create(['name' => '舊暱稱']);

        $this->actingAs($member, 'member')->put('/member/profile', ['name' => '  新暱稱  '])
            ->assertRedirect('/member/profile')
            ->assertSessionHas('status', __('member.profile.saved'));

        $this->assertSame('新暱稱', $member->fresh()->name);
    }

    public function test_the_english_page_works_too(): void
    {
        $member = Member::factory()->create(['name' => 'Old']);

        $this->actingAs($member, 'member')->get('/en/member/profile')->assertOk()->assertSee('Edit nickname');
        $this->actingAs($member, 'member')->put('/en/member/profile', ['name' => 'Bunny'])
            ->assertRedirect('/en/member/profile');

        $this->assertSame('Bunny', $member->fresh()->name);
    }

    public function test_the_nickname_is_required_and_limited_to_255_characters(): void
    {
        $member = Member::factory()->create(['name' => '原本']);

        $this->actingAs($member, 'member')->put('/member/profile', ['name' => '   '])
            ->assertSessionHasErrors('name');
        $this->actingAs($member, 'member')->put('/member/profile', ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');

        $this->assertSame('原本', $member->fresh()->name);
    }

    public function test_only_the_name_can_be_changed_through_this_form(): void
    {
        $member = Member::factory()->create(['email' => 'keep@example.com', 'is_active' => true]);

        $this->actingAs($member, 'member')->put('/member/profile', [
            'name' => '新名字',
            'email' => 'evil@example.com',
            'is_active' => false,
            'carrots' => 999,
        ])->assertRedirect();

        $fresh = $member->fresh();
        $this->assertSame('keep@example.com', $fresh->email);
        $this->assertTrue($fresh->is_active);
        $this->assertSame(0, $fresh->carrots);
    }

    public function test_guests_and_unverified_members_are_redirected(): void
    {
        $this->get('/member/profile')->assertRedirect();
        $this->put('/member/profile', ['name' => 'x'])->assertRedirect();

        $unverified = Member::factory()->unverified()->create();
        $this->actingAs($unverified, 'member')->get('/member/profile')->assertRedirect();
    }

    public function test_the_member_center_links_to_the_form(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')->get('/member')
            ->assertSee(route('member.profile.edit'), false)
            ->assertSee('編輯暱稱');
    }
}
