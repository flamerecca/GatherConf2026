<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Widgets\ResultReferralOverview;
use App\Filament\Resources\Members\Widgets\ResultReferralTable;
use App\Models\Member;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\ResultReferral;
use App\Models\User;
use Database\Seeders\PersonalityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class ResultReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PersonalityTypeSeeder::class);
    }

    private function attemptOf(?Member $member): QuizAttempt
    {
        return QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid7(), 'user_id' => $member?->id, 'scores' => [], 'type_code' => 'NVHG', 'completed_at' => now(),
        ]);
    }

    /**
     * Test requests do not keep cookies between calls, so hand them over like a browser would.
     */
    private function keepCookies(TestResponse $response): static
    {
        foreach (['gc_visitor', 'gc_own'] as $name) {
            if ($response->getCookie($name) !== null) {
                $this->withCookie($name, $response->getCookie($name)->getValue());
            }
        }

        return $this;
    }

    private function answers(): array
    {
        foreach (['NM' => 'N', 'SV' => 'S', 'HA' => 'H', 'GD' => 'G'] as $axis => $pole) {
            QuizQuestion::query()->create(['axis' => $axis, 'positive_pole' => $pole, 'text' => "題目 $axis", 'order' => QuizQuestion::count() + 1, 'is_active' => true]);
        }

        return QuizQuestion::query()->pluck('id')->mapWithKeys(fn (int $id) => [$id => 3])->all();
    }

    public function test_a_visitor_of_someone_elses_result_is_counted_once_across_visits(): void
    {
        $attempt = $this->attemptOf(Member::factory()->create());

        $first = $this->get(route('quiz.result', $attempt->uuid))->assertCookie('gc_visitor');
        $this->keepCookies($first)->get(route('quiz.result', $attempt->uuid));

        $this->assertSame(1, ResultReferral::count());
        $this->assertSame($attempt->id, ResultReferral::query()->sole()->quiz_attempt_id);
    }

    public function test_the_owner_crawlers_and_the_taker_of_the_quiz_are_not_counted(): void
    {
        $owner = Member::factory()->create();
        $attempt = $this->attemptOf($owner);

        $this->actingAs($owner, 'member')->get(route('quiz.result', $attempt->uuid));
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get(route('quiz.result', $attempt->uuid));
        $this->assertSame(0, ResultReferral::count());

        $answers = $this->answers();
        $taken = $this->withSession([QuizQuestion::SESSION_KEY => QuizQuestion::query()->pluck('id')->all()])->post(route('quiz.store'), ['answers' => $answers]);
        $mine = QuizAttempt::query()->latest('id')->first();
        $this->keepCookies($taken)->get(route('quiz.result', $mine->uuid));
        $this->assertSame(0, ResultReferral::count());
    }

    public function test_a_referred_visitor_who_finishes_the_quiz_and_registers_is_followed(): void
    {
        $attempt = $this->attemptOf(Member::factory()->create());

        $visit = $this->get(route('quiz.result', $attempt->uuid));
        $answers = $this->answers();
        $taken = $this->keepCookies($visit)->withSession([QuizQuestion::SESSION_KEY => QuizQuestion::query()->pluck('id')->all()])->post(route('quiz.store'), ['answers' => $answers]);

        $referral = ResultReferral::query()->sole();
        $this->assertNotNull($referral->completed_at);
        $this->assertNull($referral->registered_at);

        $this->keepCookies($taken)->post('/register', ['name' => '小明', 'email' => 'new@example.com']);

        $this->assertNotNull($referral->fresh()->registered_at);
    }

    public function test_the_admin_sees_the_funnel_per_member(): void
    {
        $this->actingAs(User::factory()->create());
        $member = Member::factory()->create(['name' => '推廣者']);
        $attempt = $this->attemptOf($member);

        foreach ([[now(), now()], [now(), null], [null, null]] as [$completed, $registered]) {
            ResultReferral::query()->create(['quiz_attempt_id' => $attempt->id, 'visitor_token' => (string) Str::uuid(), 'completed_at' => $completed, 'registered_at' => $registered]);
        }
        ResultReferral::query()->create(['quiz_attempt_id' => $this->attemptOf(null)->id, 'visitor_token' => (string) Str::uuid()]);

        Livewire::test(ListMembers::class)->assertSeeLivewire(ResultReferralOverview::class)->assertSeeLivewire(ResultReferralTable::class);
        Livewire::test(ResultReferralOverview::class)->assertSee('從結果頁進來')->assertSeeInOrder(['4', '2', '1']);
        Livewire::test(ResultReferralTable::class)->assertCanSeeTableRecords(collect([$member]))
            ->assertTableColumnStateSet('visitors_count', 3, $member)
            ->assertTableColumnStateSet('completed_count', 2, $member)
            ->assertTableColumnStateSet('registered_count', 1, $member);
    }
}
