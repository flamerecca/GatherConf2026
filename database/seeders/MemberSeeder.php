<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Member::factory()->count(8)->create();
        Member::factory()->unverified()->count(2)->create();
        Member::factory()->inactive()->count(2)->create();
    }
}
