<?php

namespace Tests\Feature\Member;

use App\Models\Friendship;
use App\Models\Member;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RebuildMemberTablesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(string $pattern): object
    {
        return require glob(database_path('migrations/'.$pattern))[0];
    }

    private function isIntegerColumn(string $table, string $column): bool
    {
        $type = collect(Schema::getColumns($table))->firstWhere('name', $column)['type_name'];

        return str_contains(strtolower($type), 'int');
    }

    /**
     * Put the database in the state production had: integer member keys and a
     * leftover messages table that was created before its foreign key failed.
     */
    private function restoreTheLegacyIntegerSchema(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (['messages', 'friendships', 'member_blocks', 'member_login_links', 'members'] as $table) {
                Schema::dropIfExists($table);
            }

            Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropForeign(['user_id']));
            Schema::table('quiz_attempts', fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable()->change());

            Schema::create('members', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('organization')->nullable();
                $table->string('friend_code', 32)->unique();
                $table->boolean('is_active')->default(true)->index();
                $table->rememberToken();
                $table->timestamps();
            });

            Schema::create('friendships', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_low_id')->constrained('members')->cascadeOnDelete();
                $table->foreignId('member_high_id')->constrained('members')->cascadeOnDelete();
                $table->foreignId('requester_id')->constrained('members')->cascadeOnDelete();
                $table->string('status')->default('pending');
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                $table->unique(['member_low_id', 'member_high_id']);
            });

            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sender_id');
                $table->unsignedBigInteger('recipient_id');
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        });

        DB::table('members')->insert([
            ['name' => 'Old', 'email' => 'old@example.com', 'password' => 'x', 'friend_code' => str_repeat('a', 32), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_it_does_nothing_on_a_database_that_already_uses_uuids(): void
    {
        $member = Member::factory()->create();

        $this->migration('*_rebuild_member_tables_with_uuid_keys.php')->up();

        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }

    public function test_it_rebuilds_the_legacy_integer_schema_with_uuid_keys(): void
    {
        $this->restoreTheLegacyIntegerSchema();
        $this->assertTrue($this->isIntegerColumn('members', 'id'));

        $this->migration('*_rebuild_member_tables_with_uuid_keys.php')->up();

        foreach ([
            'members' => 'id',
            'member_login_links' => 'member_id',
            'friendships' => 'member_low_id',
            'friendships ' => 'requester_id',
            'member_blocks' => 'blocker_id',
            'quiz_attempts' => 'user_id',
        ] as $table => $column) {
            $this->assertFalse($this->isIntegerColumn(trim($table), $column), "{$table}.{$column} is still an integer");
        }

        $this->assertSame(0, DB::table('members')->count());
        $this->assertFalse(Schema::hasTable('messages'));
    }

    public function test_the_messages_migration_runs_after_the_rebuild(): void
    {
        $this->restoreTheLegacyIntegerSchema();
        $this->migration('*_rebuild_member_tables_with_uuid_keys.php')->up();

        $this->migration('*_create_messages_table.php')->up();

        $this->assertTrue(Schema::hasTable('messages'));
        $this->assertFalse($this->isIntegerColumn('messages', 'sender_id'));
    }

    public function test_the_rebuilt_tables_accept_the_application_models(): void
    {
        $this->restoreTheLegacyIntegerSchema();
        $this->migration('*_rebuild_member_tables_with_uuid_keys.php')->up();
        $this->migration('*_create_messages_table.php')->up();

        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->accepted()->create();

        $this->assertTrue(Friendship::between($a, $b)->is($friendship));
        $this->assertSame(1, Member::query()->where('friend_code', $a->friend_code)->count());
    }

    public function test_running_it_twice_keeps_the_new_data(): void
    {
        $this->restoreTheLegacyIntegerSchema();
        $migration = $this->migration('*_rebuild_member_tables_with_uuid_keys.php');
        $migration->up();
        $member = Member::factory()->create();

        $migration->up();

        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }
}
