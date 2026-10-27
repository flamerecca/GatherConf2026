<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npc_characters', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('drift_bottles', function (Blueprint $table): void {
            $table->foreignId('npc_character_id')->nullable()->after('sender_id')
                ->constrained('npc_characters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drift_bottles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('npc_character_id');
        });

        Schema::dropIfExists('npc_characters');
    }
};
