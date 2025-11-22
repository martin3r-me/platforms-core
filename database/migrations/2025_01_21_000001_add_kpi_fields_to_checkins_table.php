<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            // Neue KPI-Felder für saubere Stimmung / Energie-Messung
            $table->tinyInteger('mood_score')->nullable()->after('daily_goal');   // 0–4
            $table->tinyInteger('energy_score')->nullable()->after('mood_score'); // 0–4

            // Kategorie des täglichen Ziels (optional)
            $table->string('goal_category')->nullable()->after('daily_goal');
        });
    }

    public function down(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->dropColumn(['mood_score', 'energy_score', 'goal_category']);
        });
    }
};

