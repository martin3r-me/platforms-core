<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_versions', function (Blueprint $table) {
            $table->id();
            
            // Polymorphe Beziehung zum Model
            $table->string('versionable_type');
            $table->unsignedBigInteger('versionable_id');
            $table->index(['versionable_type', 'versionable_id']);
            
            // Versionierung
            $table->integer('version_number')->default(1); // Versionsnummer (1, 2, 3, ...)
            $table->string('operation')->index(); // 'created', 'updated', 'deleted'
            
            // Snapshot (vollständiger Zustand vor/nach Änderung)
            $table->json('snapshot_before')->nullable(); // Zustand VOR der Änderung
            $table->json('snapshot_after')->nullable(); // Zustand NACH der Änderung
            $table->json('changed_fields')->nullable(); // Nur geänderte Felder (für Updates)
            
            // Kontext
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('tool_name')->nullable()->index(); // Welches Tool hat die Änderung verursacht?
            $table->string('trace_id', 32)->nullable()->index(); // Trace-ID für Request-Tracking
            $table->string('chain_id', 32)->nullable()->index(); // Chain-ID für Tool-Chains
            $table->unsignedBigInteger('tool_execution_id')->nullable()->constrained('tool_executions')->nullOnDelete();
            
            // Metadaten
            $table->text('reason')->nullable(); // Grund für die Änderung (z.B. "LLM: Projekt erstellt")
            $table->json('metadata')->nullable(); // Zusätzliche Metadaten
            
            $table->timestamps();
            
            // Indexes
            $table->index(['versionable_type', 'versionable_id', 'version_number']);
            $table->index(['tool_name', 'created_at']);
            $table->index(['trace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};

