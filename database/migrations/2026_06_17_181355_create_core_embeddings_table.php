<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('entity_type', 64);
            $table->string('entity_id', 64);
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->unsignedSmallInteger('dimensions');
            $table->json('vector');
            $table->json('metadata')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['team_id', 'entity_type', 'entity_id', 'provider', 'model'],
                'core_embeddings_unique'
            );
            // Spaltenreihenfolge folgt dem Haupt-Such-Pfad: search() filtert stets
            // team_id + provider + model und schränkt entity_type nur optional ein.
            // Daher entity_type als letzte Spalte, damit der Index auch ohne
            // entity_type-Filter (Cross-Type-Suche) voll greift.
            $table->index(
                ['team_id', 'provider', 'model', 'entity_type'],
                'core_embeddings_search_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_embeddings');
    }
};
