<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_executions', function (Blueprint $table) {
            $table->id();
            $table->string('tool_name', 255)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            
            // Execution-Details
            $table->json('arguments')->nullable(); // Tool-Argumente
            $table->boolean('success')->default(false)->index();
            $table->string('error_code', 100)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->text('error_trace')->nullable(); // Stack-Trace bei Fehlern
            
            // Performance-Metriken
            $table->integer('duration_ms')->default(0); // Dauer in Millisekunden
            $table->integer('memory_usage_bytes')->default(0); // Memory-Usage in Bytes
            $table->integer('token_usage_input')->nullable(); // Input-Tokens (wenn verfügbar)
            $table->integer('token_usage_output')->nullable(); // Output-Tokens (wenn verfügbar)
            
            // Result-Details
            $table->json('result_data')->nullable(); // Tool-Result (nur bei Success)
            $table->text('result_message')->nullable(); // Result-Message
            
            // Tracking
            $table->string('trace_id', 32)->nullable()->index(); // Trace-ID für Request-Tracking
            $table->string('chain_id', 32)->nullable()->index(); // Chain-ID für Tool-Chains
            $table->integer('chain_position')->nullable(); // Position in Chain (0 = first, 1 = second, etc.)
            
            $table->timestamps();
            
            // Indexes für Performance
            $table->index(['tool_name', 'success']);
            $table->index(['user_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index(['created_at', 'success']);
            $table->index('error_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_executions');
    }
};

