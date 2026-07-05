<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verbalization_recipes', function (Blueprint $table) {
            // Deklariert das Zeitfenster fuer "seit"-Berechnungen unabhaengig vom
            // letzten Output. Format wie "7d" / "1w" / "30d" / "1m" / "1y".
            // null = Fallback auf letzten Output (bisheriges Verhalten).
            $table->string('since_window', 16)->nullable()->after('include_natures');
        });
    }

    public function down(): void
    {
        Schema::table('verbalization_recipes', function (Blueprint $table) {
            $table->dropColumn('since_window');
        });
    }
};
