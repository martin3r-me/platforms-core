<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verbalization_recipes', function (Blueprint $table) {
            // Recipe-Level LLM-Praeferenz: { "provider": "openai", "model": "gpt-4o-2024-08-06" }
            // Beide Felder optional. Aufloesungs-Reihenfolge: Feed-Override → Recipe → Config-Default → Registry.
            $table->json('llm')->nullable()->after('guards');
        });
    }

    public function down(): void
    {
        Schema::table('verbalization_recipes', function (Blueprint $table) {
            $table->dropColumn('llm');
        });
    }
};
