<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\PersonalityType;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuizAttemptMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_id_foreign_key_points_to_members(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('quiz_attempts'))
            ->filter(fn (array $key) => $key['columns'] === ['user_id']);

        $this->assertCount(1, $foreignKeys);
        $this->assertSame('members', $foreignKeys->first()['foreign_table']);
    }

    public function test_quiz_attempt_has_member_relation_using_user_id(): void
    {
        $relation = (new QuizAttempt)->member();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('user_id', $relation->getForeignKeyName());
    }

    public function test_deleting_a_member_keeps_quiz_attempts_and_nulls_user_id(): void
    {
        PersonalityType::query()->create([
            'code' => 'SNHG',
            'name' => '測試類型',
            'summary' => '摘要',
            'description' => '說明',
        ]);
        $member = Member::factory()->create();
        $attempt = QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $member->id,
            'scores' => [],
            'type_code' => 'SNHG',
            'completed_at' => now(),
        ]);

        $this->assertTrue($attempt->member->is($member));

        $member->delete();

        $this->assertNull($attempt->fresh()->user_id);
    }

    public function test_migration_refuses_to_run_when_quiz_attempts_still_reference_users(): void
    {
        $migration = require glob(database_path('migrations/*_point_quiz_attempts_user_id_to_members.php'))[0];
        $migration->down();

        $user = User::factory()->create();
        PersonalityType::query()->create([
            'code' => 'SNHG',
            'name' => '測試類型',
            'summary' => '摘要',
            'description' => '說明',
        ]);
        QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'scores' => [],
            'type_code' => 'SNHG',
            'completed_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('quiz_attempts.user_id');

        $migration->up();
    }
}
