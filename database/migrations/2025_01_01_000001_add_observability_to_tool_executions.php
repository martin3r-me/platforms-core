<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prüfe ob Tabelle existiert (wird möglicherweise später erstellt)
        if (!Schema::hasTable('tool_executions')) {
            return;
        }
        
        Schema::table('tool_executions', function (Blueprint $table) {
            // Retries
            if (!Schema::hasColumn('tool_executions', 'retries')) {
                $table->integer('retries')->default(0)->after('success');
            }
            
            // Error-Type (Kategorisierung von Fehlern)
            if (!Schema::hasColumn('tool_executions', 'error_type')) {
                $table->enum('error_type', [
                    'validation',
                    'authorization',
                    'execution',
                    'timeout',
                    'rate_limit',
                    'circuit_breaker',
                    'unknown'
                ])->nullable()->after('error_code');
            }
            
            // Metadata (JSON) für zusätzliche Metadaten
            if (!Schema::hasColumn('tool_executions', 'metadata')) {
                $table->json('metadata')->nullable()->after('result_message');
            }
        });
    }

    public function down(): void
    {
        // Prüfe ob Tabelle existiert
        if (!Schema::hasTable('tool_executions')) {
            return;
        }
        
        Schema::table('tool_executions', function (Blueprint $table) {
            if (Schema::hasColumn('tool_executions', 'retries')) {
                $table->dropColumn('retries');
            }
            if (Schema::hasColumn('tool_executions', 'error_type')) {
                $table->dropColumn('error_type');
            }
            if (Schema::hasColumn('tool_executions', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};

