<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'mcp_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('mcp_team_id')
                    ->nullable()
                    ->after('current_team_id')
                    ->constrained('teams')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'mcp_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('mcp_team_id');
            });
        }
    }
};
