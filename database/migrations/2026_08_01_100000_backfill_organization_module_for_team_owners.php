<?php

use Illuminate\Database\Migrations\Migration;
use Platform\Core\Models\Module;
use Platform\Core\Models\Team;

/**
 * Backfill: Bestehende Team-Owner bekommen das Organization-Modul.
 *
 * Neue Teams erhalten das Modul automatisch über den created-Hook im
 * Team-Model (Team::grantOrganizationModuleToOwner()). Diese Migration zieht
 * das für alle bereits existierenden Teams nach – idempotent, doppelte Grants
 * werden übersprungen.
 */
return new class extends Migration {
    public function up(): void
    {
        // Ohne registriertes Organization-Modul gibt es nichts nachzuziehen.
        if (!Module::where('key', 'organization')->exists()) {
            return;
        }

        Team::query()->chunkById(200, function ($teams) {
            foreach ($teams as $team) {
                $team->grantOrganizationModuleToOwner();
            }
        });
    }

    public function down(): void
    {
        // Grants werden nicht zurückgenommen: Wir können nicht unterscheiden,
        // ob ein Owner das Organization-Modul durch dieses Backfill oder
        // manuell erhalten hat. Bewusst kein Rollback.
    }
};
