<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semantic_layers', function (Blueprint $table) {
            $table->id();
            $table->enum('scope_type', ['global', 'team']);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->enum('status', ['draft', 'pilot', 'production', 'archived'])->default('draft');
            $table->json('enabled_modules')->nullable();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id'], 'semantic_layers_scope_unique');
            $table->foreign('scope_id')
                ->references('id')->on('teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semantic_layers');
    }
};
