<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fehlende ON DELETE Constraints für team_id Foreign Keys.
 *
 * Ohne diese Constraints blockieren invoices und team_billable_usages
 * das Löschen von Teams (FK violation). core_chats, core_command_runs
 * und users.current_team_id hatten kein constrained() – erhalten jetzt
 * eine saubere nullOnDelete()-Referenz.
 */
return new class extends Migration
{
    public function up(): void
    {
        // invoices: constrained() → cascadeOnDelete()
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        // team_billable_usages: constrained() → cascadeOnDelete()
        Schema::table('team_billable_usages', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        // core_chats: kein FK bisher → nullOnDelete() hinzufügen
        Schema::table('core_chats', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });

        // core_command_runs: kein FK bisher → nullOnDelete() hinzufügen
        Schema::table('core_command_runs', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });

        // users.current_team_id: kein FK bisher → nullOnDelete() hinzufügen
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // invoices: zurück zu constrained() ohne cascade
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->foreign('team_id')->references('id')->on('teams');
        });

        // team_billable_usages: zurück zu constrained() ohne cascade
        Schema::table('team_billable_usages', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->foreign('team_id')->references('id')->on('teams');
        });

        // core_chats: FK entfernen
        Schema::table('core_chats', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        // core_command_runs: FK entfernen
        Schema::table('core_command_runs', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        // users.current_team_id: FK entfernen
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_team_id']);
        });
    }
};
