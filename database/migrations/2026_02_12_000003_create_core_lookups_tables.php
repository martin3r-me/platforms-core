<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Lookup-Definitionen (z.B. "Nationalität", "Krankenkasse")
        Schema::create('core_lookups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 64);        // Slug/Identifier (snake_case)
            $table->string('label', 255);      // Anzeige-Label
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // System-Lookup (nicht löschbar)
            $table->timestamps();

            $table->unique(['team_id', 'name'], 'core_lookups_team_name_unique');
            $table->index('team_id', 'core_lookups_team_idx');
        });

        // Lookup-Werte
        Schema::create('core_lookup_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lookup_id')->constrained('core_lookups')->cascadeOnDelete();
            $table->string('value', 255);      // Technischer Wert (wird gespeichert)
            $table->string('label', 255);      // Anzeige-Label
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();  // Zusätzliche Daten (z.B. Ländercodes)
            $table->timestamps();

            $table->index('lookup_id', 'core_lookup_values_lookup_idx');
            $table->unique(['lookup_id', 'value'], 'core_lookup_values_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_lookup_values');
        Schema::dropIfExists('core_lookups');
    }
};
