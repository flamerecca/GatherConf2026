<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_full_seeder_creates_members_with_uuids_and_friend_codes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(12, Member::query()->count());
        $this->assertSame(0, Member::query()->whereNull('friend_code')->count());
        $this->assertSame(12, Member::query()->distinct()->count('friend_code'));
        Member::query()->pluck('id')->each(fn (string $id) => $this->assertTrue(Str::isUuid($id)));
    }
}
