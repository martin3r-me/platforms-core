<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_entity_links', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            // Morph-Paar A (Quelle)
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id');

            // Morph-Paar B (Ziel)
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id');

            $table->string('link_type', 32)->default('related');
            $table->unsignedInteger('sort_order')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Ein Link pro Richtung + Typ
            $table->unique(
                ['team_id', 'source_type', 'source_id', 'target_type', 'target_id', 'link_type'],
                'core_entity_links_unique'
            );

            // Schnelle Suche nach Quelle
            $table->index(
                ['team_id', 'source_type', 'source_id'],
                'core_entity_links_source_index'
            );

            // Schnelle Suche nach Ziel (für bidirektionale Queries)
            $table->index(
                ['team_id', 'target_type', 'target_id'],
                'core_entity_links_target_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_entity_links');
    }
};
