<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\QuizAttempt;
use Database\Seeders\PersonalityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registration_pages_render_in_both_languages(): void
    {
        $this->get('/register')->assertOk()->assertSee('註冊會員')->assertSee('noindex', false);
        $this->get('/en/register')->assertOk()->assertSee('Sign up')->assertDontSee('註冊會員');
    }

    public function test_a_logged_in_member_cannot_open_the_registration_page(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/register')->assertRedirect('/member');
    }

    public function test_registering_from_a_result_page_binds_that_report_to_the_new_member(): void
    {
        $this->seed(PersonalityTypeSeeder::class);
        $attempt = QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid7(), 'user_id' => null, 'scores' => [], 'type_code' => 'NVHG', 'completed_at' => now(),
        ]);

        $this->get(route('quiz.result', $attempt->uuid))->assertSee(route('member.register', ['attempt' => $attempt->uuid]), false);
        $this->get(route('member.register', ['attempt' => $attempt->uuid]))->assertSee('name="attempt" value="'.$attempt->uuid.'"', false);

        $this->post('/register', ['name' => '小明', 'email' => 'new@example.com', 'attempt' => $attempt->uuid])->assertRedirect();

        $this->assertSame(Member::query()->where('email', 'new@example.com')->value('id'), $attempt->fresh()->user_id);
    }

    public function test_an_existing_email_or_an_already_bound_report_is_never_taken_over(): void
    {
        $this->seed(PersonalityTypeSeeder::class);
        $owner = Member::factory()->create();
        $make = fn (?string $userId) => QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid7(), 'user_id' => $userId, 'scores' => [], 'type_code' => 'NVHG', 'completed_at' => now(),
        ]);
        $bound = $make($owner->id);
        $free = $make(null);

        $this->post('/register', ['name' => 'A', 'email' => 'other@example.com', 'attempt' => $bound->uuid]);
        $this->post('/register', ['name' => 'B', 'email' => $owner->email, 'attempt' => $free->uuid]);

        $this->assertSame($owner->id, $bound->fresh()->user_id);
        $this->assertNull($free->fresh()->user_id);
    }
}
