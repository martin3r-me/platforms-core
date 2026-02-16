<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: Setzt team_id für bestehende modulables-Einträge,
 * bei denen team_id fehlt (0 oder NULL).
 *
 * - Für Team-Einträge (modulable_type enthält 'Team'): team_id = modulable_id
 * - Abwärtskompatibel: Ändert nur Zeilen mit team_id = 0 oder NULL.
 */
return new class extends Migration {
    public function up(): void
    {
        // Backfill team_id für Team-modulables (modulable_type = Team-Klasse)
        // Bei Team-Einträgen ist modulable_id die Team-ID selbst
        DB::table('modulables')
            ->where('modulable_type', 'like', '%Team%')
            ->where(function ($query) {
                $query->where('team_id', 0)
                    ->orWhereNull('team_id');
            })
            ->update(['team_id' => DB::raw('modulable_id')]);
    }

    public function down(): void
    {
        // Kein Rollback nötig – die Daten waren vorher inkorrekt (0 oder NULL)
    }
};
