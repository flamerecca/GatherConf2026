<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('quiz_attempts')->whereNotNull('user_id')->exists()) {
            throw new RuntimeException(
                'quiz_attempts.user_id still references users. Clear or migrate those rows before pointing it to members.'
            );
        }

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('members')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
