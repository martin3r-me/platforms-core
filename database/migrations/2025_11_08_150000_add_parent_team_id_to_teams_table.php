<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('parent_team_id')
                ->nullable()
                ->after('user_id')
                ->constrained('teams')
                ->onDelete('cascade');
            
            $table->index('parent_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['parent_team_id']);
            $table->dropIndex(['parent_team_id']);
            $table->dropColumn('parent_team_id');
        });
    }
};

