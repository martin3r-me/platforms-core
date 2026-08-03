<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stellt die Spaltenreihenfolge von core_embeddings_search_idx um.
 *
 * Vorher: (team_id, entity_type, provider, model)
 * Nachher: (team_id, provider, model, entity_type)
 *
 * Grund: MySqlJsonEmbeddingStore::search() filtert stets team_id + provider + model
 * und schränkt entity_type nur optional ein. Mit entity_type an zweiter Stelle konnte
 * der Index bei der Cross-Type-Suche (ohne entity_type-Filter) nicht voll greifen.
 *
 * Idempotent gegenüber der (bereits angepassten) Create-Migration: auf einem frischen
 * Schema droppt und erzeugt diese Migration denselben Index nur neu.
 */
return new class extends Migration
{
    private const INDEX = 'core_embeddings_search_idx';

    public function up(): void
    {
        Schema::table('core_embeddings', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
            $table->index(
                ['team_id', 'provider', 'model', 'entity_type'],
                self::INDEX
            );
        });
    }

    public function down(): void
    {
        Schema::table('core_embeddings', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
            $table->index(
                ['team_id', 'entity_type', 'provider', 'model'],
                self::INDEX
            );
        });
    }
};
