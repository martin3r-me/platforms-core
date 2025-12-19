<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_counter_definitions', function (Blueprint $table) {
            $table->id();

            // Scope-Team (Root-Team): alle Kind-Teams sehen diese Definitionen
            $table->foreignId('scope_team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->string('slug');
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['scope_team_id', 'slug'], 'team_counter_definitions_scope_slug_unique');
            $table->index(['scope_team_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_counter_definitions');
    }
};


