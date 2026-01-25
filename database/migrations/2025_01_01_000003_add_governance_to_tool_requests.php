<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prüfe ob Tabelle existiert (wird möglicherweise später erstellt)
        if (!Schema::hasTable('tool_requests')) {
            return;
        }
        
        Schema::table('tool_requests', function (Blueprint $table) {
            // Similar Requests (JSON) - IDs ähnlicher Requests
            if (!Schema::hasColumn('tool_requests', 'similar_requests')) {
                $table->json('similar_requests')->nullable()->after('similar_tools');
            }
            
            // Deduplication Key (string, indexiert) - Für Deduping
            if (!Schema::hasColumn('tool_requests', 'deduplication_key')) {
                $table->string('deduplication_key', 64)->nullable()->index()->after('similar_requests');
            }
        });
    }

    public function down(): void
    {
        // Prüfe ob Tabelle existiert
        if (!Schema::hasTable('tool_requests')) {
            return;
        }
        
        Schema::table('tool_requests', function (Blueprint $table) {
            if (Schema::hasColumn('tool_requests', 'deduplication_key')) {
                $table->dropIndex(['deduplication_key']);
                $table->dropColumn('deduplication_key');
            }
            if (Schema::hasColumn('tool_requests', 'similar_requests')) {
                $table->dropColumn('similar_requests');
            }
        });
    }
};

