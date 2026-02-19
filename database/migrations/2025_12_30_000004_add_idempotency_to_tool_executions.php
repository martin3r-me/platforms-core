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
            // Idempotency-Key (für Duplikat-Erkennung)
            if (!Schema::hasColumn('tool_executions', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->index()->after('trace_id');
            }
            
            // Is Retry (boolean)
            if (!Schema::hasColumn('tool_executions', 'is_retry')) {
                $table->boolean('is_retry')->default(false)->after('retries');
            }
            
            // Original Run ID (foreign key zu tool_executions)
            if (!Schema::hasColumn('tool_executions', 'original_run_id')) {
                $table->foreignId('original_run_id')->nullable()->constrained('tool_executions')->nullOnDelete()->after('is_retry');
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
            if (Schema::hasColumn('tool_executions', 'original_run_id')) {
                $table->dropForeign(['original_run_id']);
                $table->dropColumn('original_run_id');
            }
            if (Schema::hasColumn('tool_executions', 'is_retry')) {
                $table->dropColumn('is_retry');
            }
            if (Schema::hasColumn('tool_executions', 'idempotency_key')) {
                $table->dropIndex(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
        });
    }
};

