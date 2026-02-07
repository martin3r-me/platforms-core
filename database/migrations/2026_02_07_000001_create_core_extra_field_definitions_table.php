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
        Schema::create('core_extra_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Kontext-Typ (z.B. "Platform\Hcm\Models\HcmApplicant")
            $table->string('context_type');
            $table->unsignedBigInteger('context_id')->nullable(); // null = für ALLE dieses Typs

            // Feld-Konfiguration
            $table->string('name', 64);           // Slug/Identifier (snake_case)
            $table->string('label', 255);         // Anzeige-Label
            $table->string('type', 32);           // text, number, textarea
            $table->boolean('is_required')->default(false);
            $table->boolean('is_encrypted')->default(false); // Wert verschlüsselt speichern
            $table->integer('order')->default(0);
            $table->json('options')->nullable();  // Typ-spezifische Optionen (z.B. min/max für number)

            $table->timestamps();

            // Indices
            $table->index(['team_id', 'context_type', 'context_id']);
            $table->unique(['team_id', 'context_type', 'context_id', 'name'], 'extra_field_def_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_extra_field_definitions');
    }
};
