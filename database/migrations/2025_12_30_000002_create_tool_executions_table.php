<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prüfe ob Tabelle bereits existiert (z.B. bei fehlgeschlagener Migration)
        if (Schema::hasTable('tool_executions')) {
            // Tabelle existiert bereits - füge nur fehlende Indexe hinzu
            try {
                Schema::table('tool_executions', function (Blueprint $table) {
                    // Versuche Index hinzuzufügen - wird fehlschlagen, wenn er bereits existiert
                    $table->index('error_code');
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Index existiert bereits - das ist OK, einfach weitermachen
                if (str_contains($e->getMessage(), 'Duplicate key name')) {
                    // Index existiert bereits - nichts zu tun
                    return;
                }
                // Anderer Fehler - weiterwerfen
                throw $e;
            }
            return;
        }
        
        // Tabelle existiert nicht - erstelle sie neu
        Schema::create('tool_executions', function (Blueprint $table) {
            $table->id();
            $table->string('tool_name', 255)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            
            // Execution-Details
            $table->json('arguments')->nullable(); // Tool-Argumente
            $table->boolean('success')->default(false)->index();
            $table->string('error_code', 100)->nullable(); // Index wird unten hinzugefügt
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
            $table->index('error_code'); // Index für error_code (nur einmal!)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_executions');
    }
};

