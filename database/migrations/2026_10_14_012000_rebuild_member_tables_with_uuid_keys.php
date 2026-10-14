<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members originally used integer keys. Environments that already ran the
     * older migrations are rebuilt here with UUID keys. The member tables hold
     * no data worth keeping at that point, so they are dropped and recreated.
     * A database created from the current migrations is left untouched.
     */
    public function up(): void
    {
        if (! $this->membersUseIntegerKeys()) {
            return;
        }

        Schema::withoutForeignKeyConstraints(function (): void {
            // A failed earlier deploy can leave a messages table without foreign keys.
            Schema::dropIfExists('messages');
            Schema::dropIfExists('friendships');
            Schema::dropIfExists('member_blocks');
            Schema::dropIfExists('member_login_links');

            DB::table('quiz_attempts')->update(['user_id' => null]);

            Schema::table('quiz_attempts', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::dropIfExists('members');

            Schema::create('members', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('organization')->nullable();
                $table->string('friend_code', 32)->unique();
                $table->boolean('is_active')->default(true)->index();
                $table->rememberToken();
                $table->timestamps();
            });

            Schema::table('quiz_attempts', function (Blueprint $table) {
                $table->uuid('user_id')->nullable()->change();
            });

            Schema::table('quiz_attempts', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('members')->nullOnDelete();
            });

            Schema::create('member_login_links', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
                $table->string('token_hash')->unique();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });

            Schema::create('friendships', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('member_low_id')->constrained('members')->cascadeOnDelete();
                $table->foreignUuid('member_high_id')->constrained('members')->cascadeOnDelete();
                $table->foreignUuid('requester_id')->constrained('members')->cascadeOnDelete();
                $table->string('status')->default('pending');
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->unique(['member_low_id', 'member_high_id']);
            });

            Schema::create('member_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('blocker_id')->constrained('members')->cascadeOnDelete();
                $table->foreignUuid('blocked_id')->constrained('members')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['blocker_id', 'blocked_id']);
            });
        });
    }

    /**
     * The rebuild cannot be reversed because the previous data was discarded.
     */
    public function down(): void
    {
        //
    }

    private function membersUseIntegerKeys(): bool
    {
        if (! Schema::hasTable('members')) {
            return false;
        }

        $id = collect(Schema::getColumns('members'))->firstWhere('name', 'id');

        return $id !== null && str_contains(strtolower($id['type_name']), 'int');
    }
};
