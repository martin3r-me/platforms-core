<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verbalization_recipes', function (Blueprint $table) {
            // Fact-Nature-Filter: welche Ebenen (state/movement/derivation) sollen
            // in der Prosa auftauchen? null = alle. Beispiele:
            //   ["state"]                  → reiner Zustandsbericht (Wall-Display)
            //   ["movement", "derivation"] → Change-Ticker
            //   ["state","movement","derivation"] oder null → Hybrid (Standard)
            $table->json('include_natures')->nullable()->after('llm');
        });
    }

    public function down(): void
    {
        Schema::table('verbalization_recipes', function (Blueprint $table) {
            $table->dropColumn('include_natures');
        });
    }
};
