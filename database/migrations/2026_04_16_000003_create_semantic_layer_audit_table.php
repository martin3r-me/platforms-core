<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semantic_layer_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semantic_layer_id')
                ->constrained('semantic_layers')
                ->cascadeOnDelete();
            $table->foreignId('version_id')
                ->nullable()
                ->constrained('semantic_layer_versions')
                ->nullOnDelete();
            $table->string('action', 40);
            $table->json('diff')->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['semantic_layer_id', 'created_at'], 'semantic_layer_audit_layer_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semantic_layer_audit');
    }
};
