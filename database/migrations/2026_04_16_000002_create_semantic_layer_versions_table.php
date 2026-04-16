<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semantic_layer_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semantic_layer_id')
                ->constrained('semantic_layers')
                ->cascadeOnDelete();
            $table->string('semver', 20);
            $table->enum('version_type', ['major', 'minor', 'patch']);
            $table->text('perspektive');
            $table->json('ton');
            $table->json('heuristiken');
            $table->json('negativ_raum');
            $table->integer('token_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['semantic_layer_id', 'semver'], 'semantic_layer_versions_semver_unique');
        });

        // FK from semantic_layers.current_version_id -> semantic_layer_versions.id
        Schema::table('semantic_layers', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')->on('semantic_layer_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('semantic_layers', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('semantic_layer_versions');
    }
};
