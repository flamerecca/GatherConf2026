<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('friend_code', 32)->nullable()->after('organization');
        });

        DB::table('members')->whereNull('friend_code')->orderBy('id')->each(function (object $member): void {
            DB::table('members')->where('id', $member->id)->update([
                'friend_code' => Str::lower(Str::random(32)),
            ]);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('friend_code', 32)->nullable(false)->change();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->unique('friend_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['friend_code']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('friend_code');
        });
    }
};
