<?php

use App\Models\PersonalityType;
use Database\Seeders\PersonalityTypeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personality_types', function (Blueprint $table): void {
            $table->text('career_advice')->nullable()->after('weaknesses');
            $table->json('growth_directions')->nullable()->after('career_advice');
        });

        foreach (PersonalityTypeSeeder::guidance() as $code => $guidance) {
            PersonalityType::withTrashed()->where('code', $code)->get()->each->forceFill($guidance)->each->save();
        }
    }

    public function down(): void
    {
        Schema::table('personality_types', function (Blueprint $table): void {
            $table->dropColumn(['career_advice', 'growth_directions']);
        });
    }
};
