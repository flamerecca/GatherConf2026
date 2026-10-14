<?php

namespace Tests\Feature\Friends;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class FriendCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_members_get_a_unique_32_character_lowercase_code(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $a->friend_code);
        $this->assertNotSame($a->friend_code, $b->friend_code);
    }

    public function test_the_code_is_not_serialized(): void
    {
        $member = Member::factory()->create();

        $this->assertArrayNotHasKey('friend_code', $member->toArray());
        $this->assertStringNotContainsString($member->friend_code, $member->toJson());
    }

    public function test_regenerating_replaces_the_code(): void
    {
        $member = Member::factory()->create();
        $old = $member->friend_code;

        $member->regenerateFriendCode();

        $this->assertNotSame($old, $member->fresh()->friend_code);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $member->fresh()->friend_code);
    }

    public function test_friend_url_is_localized_and_contains_the_code(): void
    {
        Route::get('/friends/add/{code}', fn () => 'ok')->name('member.friends.add');
        Route::get('/en/friends/add/{code}', fn () => 'ok')->name('member.friends.add.en');
        Route::getRoutes()->refreshNameLookups();
        $member = Member::factory()->create();

        $this->assertSame(url('/friends/add/'.$member->friend_code), $member->friendUrl('zh_TW'));
        $this->assertSame(url('/en/friends/add/'.$member->friend_code), $member->friendUrl('en'));
    }

    public function test_the_migration_backfills_existing_members(): void
    {
        $migration = require glob(database_path('migrations/*_add_friend_code_to_members_table.php'))[0];
        $migration->down();

        DB::table('members')->insert([
            ['id' => (string) Str::uuid7(), 'name' => 'A', 'email' => 'a@example.com', 'password' => 'x', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid7(), 'name' => 'B', 'email' => 'b@example.com', 'password' => 'x', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration->up();

        $codes = DB::table('members')->pluck('friend_code');
        $this->assertCount(2, $codes);
        $this->assertCount(2, $codes->unique());
        $codes->each(fn ($code) => $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $code));
    }
}
