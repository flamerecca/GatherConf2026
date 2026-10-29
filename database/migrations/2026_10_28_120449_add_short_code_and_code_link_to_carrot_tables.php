<?php

use App\Models\CarrotTask;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrot_tasks', function (Blueprint $table): void {
            $table->string('short_code', 32)->nullable()->unique()->after('code');
        });

        CarrotTask::query()->whereNull('short_code')->each(function (CarrotTask $task): void {
            $task->forceFill(['short_code' => CarrotTask::newShortCode()])->save();
        });

        Schema::table('carrot_transactions', function (Blueprint $table): void {
            $table->foreignUuid('authorization_code_id')->nullable()->after('task_id')
                ->constrained('authorization_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carrot_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('authorization_code_id');
        });

        Schema::table('carrot_tasks', function (Blueprint $table): void {
            $table->dropColumn('short_code');
        });
    }
};
