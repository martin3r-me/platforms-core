<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 3-Wege Team-Kontext-Trennung: UI / MCP / Playground
 *
 * - Erstellt mcp_sessions Tabelle (pro SSE-Verbindung ein eigener Team-Kontext)
 * - Fügt team_id zu core_chat_threads hinzu (Playground-Thread behält sein Team)
 * - Entfernt mcp_team_id von users (wird durch mcp_sessions ersetzt)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. MCP Sessions Tabelle erstellen
        if (!Schema::hasTable('mcp_sessions')) {
            Schema::create('mcp_sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }

        // 2. Playground: jeder Thread gehört zu einem Team
        if (!Schema::hasColumn('core_chat_threads', 'team_id')) {
            Schema::table('core_chat_threads', function (Blueprint $table) {
                $table->foreignId('team_id')
                    ->nullable()
                    ->after('core_chat_id')
                    ->constrained('teams')
                    ->nullOnDelete();
            });
        }

        // 3. mcp_team_id von users entfernen (ersetzt durch mcp_sessions.team_id)
        if (Schema::hasColumn('users', 'mcp_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('mcp_team_id');
            });
        }
    }

    public function down(): void
    {
        // mcp_team_id wiederherstellen
        if (!Schema::hasColumn('users', 'mcp_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('mcp_team_id')
                    ->nullable()
                    ->after('current_team_id')
                    ->constrained('teams')
                    ->nullOnDelete();
            });
        }

        // team_id von core_chat_threads entfernen
        if (Schema::hasColumn('core_chat_threads', 'team_id')) {
            Schema::table('core_chat_threads', function (Blueprint $table) {
                $table->dropConstrainedForeignId('team_id');
            });
        }

        // mcp_sessions Tabelle löschen
        Schema::dropIfExists('mcp_sessions');
    }
};
