<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('community_slug', 60);
            $table->string('community_name');
            $table->string('subject', 100);
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_messages');
    }
};
