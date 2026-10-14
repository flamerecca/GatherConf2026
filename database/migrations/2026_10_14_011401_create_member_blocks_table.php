<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('blocker_id')->constrained('members')->cascadeOnDelete();
            $table->foreignUuid('blocked_id')->constrained('members')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['blocker_id', 'blocked_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_blocks');
    }
};
