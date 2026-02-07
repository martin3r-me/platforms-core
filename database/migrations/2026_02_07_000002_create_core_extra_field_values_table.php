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
        // Drop if exists for safe re-runs
        Schema::dropIfExists('core_extra_field_values');

        Schema::create('core_extra_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('core_extra_field_definitions')->cascadeOnDelete();

            // Polymorphe Beziehung zum Ziel-Objekt
            $table->string('fieldable_type');
            $table->unsignedBigInteger('fieldable_id');

            // Wert (als Text gespeichert, Casting im Model)
            $table->text('value')->nullable();

            $table->timestamps();

            // Indices (with explicit short names to avoid MySQL 64-char limit)
            $table->index(['fieldable_type', 'fieldable_id'], 'extra_field_val_fieldable_idx');
            $table->unique(['definition_id', 'fieldable_type', 'fieldable_id'], 'extra_field_value_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_extra_field_values');
    }
};
