<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\Embedding;
use Platform\Core\Services\EmbeddingStoreRegistry;

/**
 * Verschiebt bestehende Embeddings eines Entity-Types von MySQL in einen anderen
 * Store (i.d.R. Qdrant), nachdem das Routing für diesen Entity-Type umgestellt wurde.
 *
 * Wichtig: Es werden die vorhandenen Vektoren 1:1 KOPIERT — kein Neu-Embedden,
 * keine Provider-/API-Kosten. provider, model, source_hash und metadata bleiben
 * erhalten, damit Skip-if-unchanged nach der Migration weiter funktioniert.
 *
 * Quelle ist immer der MySQL-Store (core_embeddings) — dort liegen die Altdaten.
 * Ziel ist der aktuell für den Entity-Type geroutete Store, oder explizit via --to.
 *
 * Der Kopiervorgang ist idempotent (Ziel-Stores upserten anhand identifizierender
 * Felder), kann also gefahrlos wiederholt werden. Erst --purge löscht die
 * Quell-Rows in MySQL — und zwar nur nach erfolgreichem Kopieren.
 */
class MigrateEmbeddingStoreCommand extends Command
{
    protected $signature = 'embeddings:migrate-store
        {entityType : Entity-Type, dessen Embeddings verschoben werden (z.B. recipe).}
        {--to= : Ziel-Store-Name (Default: der aktuell für diesen entity_type geroutete Store).}
        {--team= : Nur dieses Team migrieren (Default: alle Teams).}
        {--purge : Quell-Rows in MySQL nach erfolgreichem Kopieren löschen.}
        {--dry-run : Nur zählen und anzeigen, nichts schreiben.}
        {--chunk=200 : Chunk-Größe für die Iteration.}';

    protected $description = 'Kopiert Embeddings eines Entity-Types von MySQL in den (gerouteten) Ziel-Store, z.B. Qdrant.';

    public function handle(EmbeddingStoreRegistry $registry): int
    {
        $entityType = (string) $this->argument('entityType');
        $teamFilter = $this->option('team') !== null ? (int) $this->option('team') : null;
        $purge = (bool) $this->option('purge');
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        // --- Ziel-Store bestimmen ---
        $toName = $this->option('to');
        if ($toName !== null && $toName !== '') {
            if (! $registry->has((string) $toName)) {
                $this->error("Ziel-Store '{$toName}' ist nicht registriert. Verfügbar: " . implode(', ', $registry->names()));
                return self::FAILURE;
            }
            $target = $registry->get((string) $toName);
            $targetLabel = (string) $toName;
        } else {
            // Auflösung über das aktuelle Routing des Entity-Types.
            $target = $registry->resolve(null, $entityType);
            $targetLabel = $this->storeName($registry, $target) ?? '(unbekannt)';
        }

        // --- Quelle = MySQL-Store; Sinnlos, wenn Ziel identisch ist ---
        $mysql = $registry->has('mysql') ? $registry->get('mysql') : null;
        if ($mysql !== null && $target === $mysql) {
            $this->warn(
                "Ziel-Store ist MySQL — nichts zu tun. Entweder Routing für '{$entityType}' "
                . "umstellen (Registry::route()/config) oder Ziel via --to=qdrant angeben."
            );
            return self::SUCCESS;
        }

        // --- Quell-Query ---
        $query = Embedding::query()->where('entity_type', $entityType);
        if ($teamFilter !== null) {
            $query->where('team_id', $teamFilter);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info("Keine MySQL-Embeddings für entity_type='{$entityType}'" . ($teamFilter !== null ? " (team {$teamFilter})" : '') . ' gefunden.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s%d Embedding(s) für entity_type=%s → Store "%s"%s.',
            $dryRun ? '[DRY-RUN] ' : '',
            $total,
            $entityType,
            $targetLabel,
            $purge ? ', danach MySQL-Purge' : '',
        ));

        if ($dryRun) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $copied = 0;
        $failed = 0;

        $query->chunkById($chunk, function ($rows) use ($target, &$copied, &$failed, $bar) {
            foreach ($rows as $row) {
                try {
                    $target->store(
                        teamId: (int) $row->team_id,
                        entityType: (string) $row->entity_type,
                        entityId: (string) $row->entity_id,
                        vector: (array) $row->vector,
                        provider: (string) $row->provider,
                        model: (string) $row->model,
                        sourceHash: $row->source_hash !== null ? (string) $row->source_hash : null,
                        metadata: $row->metadata !== null ? (array) $row->metadata : null,
                    );
                    $copied++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Fehler bei team={$row->team_id} id={$row->entity_id}: " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Kopiert: {$copied}" . ($failed > 0 ? " · Fehlgeschlagen: {$failed}" : ''));

        if ($failed > 0) {
            $this->error('Es gab Fehler — MySQL-Rows werden NICHT gepurgt. Problem prüfen und Command erneut ausführen (idempotent).');
            return self::FAILURE;
        }

        if ($purge) {
            $deleted = $query->delete();
            $this->info("MySQL-Purge: {$deleted} Row(s) gelöscht.");
        } else {
            $this->line('Quell-Rows in MySQL bleiben erhalten (kein --purge). Nach Verifikation ggf. mit --purge nachziehen.');
        }

        return self::SUCCESS;
    }

    /**
     * Rückwärts-Lookup: Name eines Store-Objekts anhand der Registry.
     */
    private function storeName(EmbeddingStoreRegistry $registry, object $store): ?string
    {
        foreach ($registry->names() as $name) {
            if ($registry->get($name) === $store) {
                return $name;
            }
        }
        return null;
    }
}
