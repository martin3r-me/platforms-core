<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\Module;
use Platform\Core\PlatformCore;
use Platform\Core\Registry\ModuleRegistry;

/**
 * Raeumt Rows aus der modules-Tabelle auf, deren Modul in diesem Prozess
 * NICHT mehr ueber PlatformCore::registerModule() gemeldet wurde.
 *
 * Anwendungsfall:
 *   - Modul-Package entfernt oder Registrierung im ServiceProvider zurueckgezogen
 *   - Der DB-Row bleibt sonst zurueck und taucht in der Modul-Matrix auf
 *
 * Sicher im Console-Context: alle ServiceProvider werden geladen, die
 * ModuleRegistry ist damit vollstaendig. Beim Web-Context nicht anwenden —
 * Requests koennen mit teilweiser Provider-Ladung enden.
 *
 * Dry-Run per Default. Mit --force werden verwaiste Rows tatsaechlich geloescht.
 */
class PruneStaleModulesCommand extends Command
{
    protected $signature = 'platform:modules:prune
        {--force : Verwaiste Rows tatsaechlich loeschen (sonst nur Anzeige).}
        {--exclude= : Kommagetrennte Keys, die niemals geloescht werden sollen (Sicherheitsnetz).}';

    protected $description = 'Loescht modules-Rows, deren Modul in diesem Prozess nicht registriert wurde.';

    public function handle(): int
    {
        $registered = collect(ModuleRegistry::all())->pluck('key')->filter()->all();
        $dbKeys = Module::query()->pluck('key')->filter()->all();

        $excluded = collect(explode(',', (string) $this->option('exclude')))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->all();

        $stale = array_values(array_diff($dbKeys, $registered, $excluded));

        if (empty($stale)) {
            $this->info('Keine verwaisten Modul-Rows gefunden.');
            $this->line('  Registriert: ' . count($registered));
            $this->line('  In DB:       ' . count($dbKeys));
            return self::SUCCESS;
        }

        $this->warn('Verwaiste Modul-Rows gefunden:');
        foreach ($stale as $key) {
            $this->line('  - ' . $key);
        }
        $this->line('');
        $this->line('  Registriert (Registry): ' . count($registered));
        $this->line('  In DB:                  ' . count($dbKeys));
        $this->line('  Verwaist:               ' . count($stale));
        if (! empty($excluded)) {
            $this->line('  Ausgeschlossen:         ' . implode(', ', $excluded));
        }

        if (! $this->option('force')) {
            $this->line('');
            $this->comment('Dry-Run. Zum Loeschen: --force ergaenzen.');
            return self::SUCCESS;
        }

        $deleted = Module::query()->whereIn('key', $stale)->delete();
        $this->info("Geloescht: {$deleted} Modul-Rows.");
        return self::SUCCESS;
    }
}
