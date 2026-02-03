<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_file_references', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Referenz zum ContextFile
            $table->foreignId('context_file_id')
                ->constrained('context_files')
                ->cascadeOnDelete();

            // Polymorph: Wohin zeigt die Referenz?
            // z.B. LocationGalleryBoard::class + board_id
            // z.B. LocationContentBoardItem::class + item_id
            // z.B. Brand::class + brand_id
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->index(['reference_type', 'reference_id'], 'cfr_reference_index');

            // Sortierung (für Galerien etc.)
            $table->integer('order')->default(0);

            // Optionale Metadaten (title override, caption, alt text, etc.)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Ein File kann nur einmal pro Referenz existieren
            $table->unique(
                ['context_file_id', 'reference_type', 'reference_id'],
                'cfr_unique_reference'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_file_references');
    }
};
