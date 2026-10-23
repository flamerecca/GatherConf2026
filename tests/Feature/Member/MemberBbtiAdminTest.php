<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\Widgets\BbtiDistributionChart;
use App\Models\Member;
use App\Models\PersonalityType;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MemberBbtiAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        foreach (['SNHG' => '甲兔', 'VNHG' => '乙兔'] as $code => $name) {
            PersonalityType::query()->create(['code' => $code, 'name' => $name, 'summary' => '摘要', 'description' => '說明']);
        }
    }

    private function attempt(Member $member, string $code, string $completedAt = 'now'): void
    {
        QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $member->id,
            'scores' => [],
            'type_code' => $code,
            'completed_at' => $completedAt,
        ]);
    }

    public function test_list_shows_each_members_latest_bbti_and_a_placeholder_when_untaken(): void
    {
        $taken = Member::factory()->create();
        $this->attempt($taken, 'SNHG', '2026-01-01');
        $this->attempt($taken, 'VNHG', '2026-02-01');
        $untaken = Member::factory()->create();

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$taken, $untaken])
            ->assertTableColumnStateSet('latestQuizAttempt.type_code', 'VNHG', $taken)
            ->assertTableColumnStateSet('latestQuizAttempt.type_code', null, $untaken);
    }

    public function test_list_can_be_filtered_by_bbti_type(): void
    {
        $snhg = Member::factory()->create();
        $this->attempt($snhg, 'SNHG');
        $vnhg = Member::factory()->create();
        $this->attempt($vnhg, 'VNHG');
        $untaken = Member::factory()->create();

        Livewire::test(ListMembers::class)
            ->filterTable('bbti', 'SNHG')
            ->assertCanSeeTableRecords([$snhg])
            ->assertCanNotSeeTableRecords([$vnhg, $untaken]);
    }

    public function test_filter_uses_only_the_latest_attempt(): void
    {
        $member = Member::factory()->create();
        $this->attempt($member, 'SNHG', '2026-01-01');
        $this->attempt($member, 'VNHG', '2026-02-01');

        Livewire::test(ListMembers::class)
            ->filterTable('bbti', 'SNHG')
            ->assertCanNotSeeTableRecords([$member]);
    }

    public function test_distribution_counts_each_members_latest_result_only(): void
    {
        $changed = Member::factory()->create();
        $this->attempt($changed, 'SNHG', '2026-01-01');
        $this->attempt($changed, 'VNHG', '2026-02-01');
        $this->attempt(Member::factory()->create(), 'VNHG');
        Member::factory()->create();

        $component = Livewire::test(BbtiDistributionChart::class);
        $data = (fn (): array => $this->getData())->call($component->instance());

        $this->assertSame(['SNHG 甲兔', 'VNHG 乙兔'], $data['labels']);
        $this->assertSame([0, 2], $data['datasets'][0]['data']);
        $component->assertSee('共 2 位會員');
    }

    public function test_list_page_renders_the_distribution_widget(): void
    {
        Livewire::test(ListMembers::class)->assertSeeLivewire(BbtiDistributionChart::class);
    }

    public function test_view_page_shows_the_members_bbti(): void
    {
        $member = Member::factory()->create();
        $this->attempt($member, 'SNHG');

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->assertSee('SNHG 甲兔');
    }

    public function test_view_page_shows_a_placeholder_for_a_member_without_a_result(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->assertSee('尚未測驗');
    }
}
