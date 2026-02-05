<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_core_ai_models', function (Blueprint $table) {
            $table->id();

            // Scope-Team (Root-Team): alle Kind-Teams erben diese Auswahl
            $table->foreignId('scope_team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->foreignId('core_ai_model_id')
                ->constrained('core_ai_models')
                ->cascadeOnDelete();

            $table->boolean('is_enabled')->default(true);

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['scope_team_id', 'core_ai_model_id'], 'team_core_ai_models_scope_model_unique');
            $table->index(['scope_team_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_core_ai_models');
    }
};
