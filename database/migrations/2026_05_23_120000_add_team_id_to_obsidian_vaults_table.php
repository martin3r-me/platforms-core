<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obsidian_vaults', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index('team_id', 'obsidian_vaults_team_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('obsidian_vaults', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex('obsidian_vaults_team_id_index');
            $table->dropColumn('team_id');
        });
    }
};
