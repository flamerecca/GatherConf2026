<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionUserIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');
    }

    public function test_the_sessions_user_id_column_can_hold_a_uuid(): void
    {
        $column = collect(Schema::getColumns('sessions'))->firstWhere('name', 'user_id');

        $this->assertStringNotContainsString('int', strtolower($column['type_name']));
        $this->assertTrue($column['nullable']);
    }

    public function test_the_user_id_column_is_still_indexed(): void
    {
        $indexed = collect(Schema::getIndexes('sessions'))->contains(fn (array $index) => $index['columns'] === ['user_id']);

        $this->assertTrue($indexed);
    }

    public function test_a_uuid_user_id_round_trips_through_the_sessions_table(): void
    {
        $member = Member::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-with-a-uuid-user',
            'user_id' => $member->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        $this->assertSame($member->id, DB::table('sessions')->where('id', 'session-with-a-uuid-user')->value('user_id'));
    }

    public function test_an_admin_session_still_stores_the_integer_user_id(): void
    {
        $this->useDatabaseSessions();
        $admin = User::factory()->create(['email' => 'flamerecca711@gmail.com']);

        $this->actingAs($admin)->get('/admin')->assertOk();

        $this->assertTrue(DB::table('sessions')->where('user_id', (string) $admin->id)->exists());
    }
}
