<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Der Verbalization-Baukasten wurde kurz als eigenes Modul via
     * PlatformCore::registerModule registriert und danach zurueckgezogen (die
     * Seite lebt jetzt sinngemaess in der Organization-Sidebar). PlatformCore
     * persistiert Modul-Configs in der modules-Tabelle — der Row bleibt bis
     * er explizit geloescht wird und taucht sonst in der Modul-Matrix auf.
     */
    public function up(): void
    {
        if (Schema::hasTable('modules')) {
            DB::table('modules')->where('key', 'verbalization')->delete();
        }
    }

    public function down(): void
    {
        // Kein Rollback — der Modul-Eintrag soll nicht wiederkommen.
    }
};
