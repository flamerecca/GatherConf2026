<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drift_bottles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('members')->cascadeOnDelete();
            $table->foreignUuid('recipient_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('body', 300);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->string('reply_body', 300)->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('reply_removed_at')->nullable();
            $table->json('released_recipient_ids')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'opened_at']);
            $table->index(['sender_id', 'created_at']);
        });

        Schema::create('drift_bottle_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('bottle_id')->constrained('drift_bottles')->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('members')->cascadeOnDelete();
            $table->string('target', 10);
            $table->string('reason', 200)->nullable();
            $table->string('outcome', 20)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['bottle_id', 'reporter_id', 'target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drift_bottle_reports');
        Schema::dropIfExists('drift_bottles');
    }
};
