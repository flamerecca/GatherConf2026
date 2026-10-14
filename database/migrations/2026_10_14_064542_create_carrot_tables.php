<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrot_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('reward');
            $table->string('code', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('carrot_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason', 40);
            $table->string('note')->nullable();
            $table->foreignUuid('task_id')->nullable()->constrained('carrot_tasks')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('dedupe_key', 120)->unique();
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->unsignedInteger('carrots')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('carrots');
        });

        Schema::dropIfExists('carrot_transactions');
        Schema::dropIfExists('carrot_tasks');
    }
};
