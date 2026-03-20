<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MCP Sessions: pro SSE-Verbindung ein eigener Team-Kontext
        Schema::create('mcp_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // Playground: jeder Thread gehört zu einem Team
        Schema::table('core_chat_threads', function (Blueprint $table) {
            $table->foreignId('team_id')
                ->nullable()
                ->after('core_chat_id')
                ->constrained('teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('core_chat_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::dropIfExists('mcp_sessions');
    }
};
