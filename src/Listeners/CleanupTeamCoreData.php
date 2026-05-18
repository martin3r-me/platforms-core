<?php

namespace Platform\Core\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Platform\Core\Events\TeamDeleting;
use Platform\Core\Models\ContextFile;

/**
 * Listener: Core-eigener Cleanup beim Loeschen eines Teams.
 *
 * Wird synchron innerhalb der DB-Transaction ausgefuehrt.
 * Bei Fehlern rollt die gesamte Transaction zurueck.
 *
 * Aufgaben:
 * - ContextFiles von Storage loeschen (Dateien auf Disk)
 * - Weitere Core-spezifische Cleanups bei Bedarf
 */
class CleanupTeamCoreData
{
    public function handle(TeamDeleting $event): void
    {
        $team = $event->team;
        $teamId = $team->id;

        Log::info('[CleanupTeamCoreData] Starte Core-Cleanup fuer Team', [
            'team_id' => $teamId,
            'team_name' => $team->name,
            'deleted_by' => $event->deletedByUserId,
        ]);

        // ContextFiles: Storage-Dateien loeschen
        $this->cleanupContextFiles($teamId);
    }

    /**
     * Loescht die physischen Dateien der ContextFiles eines Teams.
     * DB-Rows werden via cascadeOnDelete geloescht.
     */
    private function cleanupContextFiles(int $teamId): void
    {
        if (!class_exists(ContextFile::class)) {
            return;
        }

        try {
            $files = ContextFile::where('team_id', $teamId)->get();
            $deletedCount = 0;

            foreach ($files as $file) {
                $disk = $file->disk ?? 'local';
                $path = $file->path ?? null;

                if ($path && Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                    $deletedCount++;
                }
            }

            if ($deletedCount > 0) {
                Log::info('[CleanupTeamCoreData] ContextFiles von Storage geloescht', [
                    'team_id' => $teamId,
                    'files_deleted' => $deletedCount,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[CleanupTeamCoreData] Fehler beim Loeschen von ContextFiles', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);
            // Re-throw damit Transaction zurueckrollt
            throw $e;
        }
    }
}
