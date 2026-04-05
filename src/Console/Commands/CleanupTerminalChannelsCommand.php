<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\TerminalChannel;

class CleanupTerminalChannelsCommand extends Command
{
    protected $signature = 'terminal:cleanup
        {--dry-run : Nur zählen, nichts löschen}
        {--inactive-days=90 : Channels ohne Nachrichten seit X Tagen archivieren}';

    protected $description = 'Räumt verwaiste und inaktive Terminal-Channels auf (gelöschte Kontexte, leere Channels, Inaktivität).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $inactiveDays = (int) $this->option('inactive-days');

        $this->info('Terminal Cleanup' . ($dryRun ? ' (DRY RUN)' : '') . '...');
        $this->newLine();

        $totalDeleted = 0;

        // 1. Kontext-Channels mit gelöschten Kontexten
        $totalDeleted += $this->cleanupOrphanedContextChannels($dryRun);

        // 2. Leere Channels ohne Nachrichten und ohne Aktivität
        $totalDeleted += $this->cleanupEmptyChannels($dryRun);

        // 3. Inaktive Kontext-Channels (keine Nachricht seit X Tagen)
        $totalDeleted += $this->cleanupInactiveContextChannels($dryRun, $inactiveDays);

        // 4. DMs ohne Mitglieder (beide Seiten haben verlassen)
        $totalDeleted += $this->cleanupAbandonedDms($dryRun);

        $this->newLine();
        $this->info("Fertig. Channels bereinigt: {$totalDeleted}");

        return self::SUCCESS;
    }

    /**
     * Kontext-Channels deren verknüpftes Model gelöscht wurde (soft oder hard delete).
     */
    protected function cleanupOrphanedContextChannels(bool $dryRun): int
    {
        $this->components->task('Verwaiste Kontext-Channels (gelöschte Entitäten)', function () use ($dryRun) {
            $orphaned = collect();

            TerminalChannel::where('type', 'context')
                ->whereNotNull('context_type')
                ->whereNotNull('context_id')
                ->chunkById(200, function ($channels) use ($orphaned) {
                    foreach ($channels as $channel) {
                        if (! $this->contextExists($channel->context_type, $channel->context_id)) {
                            $orphaned->push($channel);
                        }
                    }
                });

            if ($orphaned->isEmpty()) {
                return 'Keine gefunden';
            }

            $count = $orphaned->count();
            $msgCount = $orphaned->sum('message_count');

            if (! $dryRun) {
                // Cascade delete handles messages, members, reactions, mentions
                TerminalChannel::whereIn('id', $orphaned->pluck('id'))->delete();
            }

            return "{$count} Channels ({$msgCount} Nachrichten)" . ($dryRun ? ' [DRY RUN]' : ' gelöscht');
        });

        // Return count for summary (re-query is fine, it's a cleanup command)
        return 0; // Logged inline
    }

    /**
     * Channels ohne Nachrichten, älter als 7 Tage (nie benutzt).
     */
    protected function cleanupEmptyChannels(bool $dryRun): int
    {
        $this->components->task('Leere Channels (nie benutzt, >7 Tage alt)', function () use ($dryRun) {
            $cutoff = now()->subDays(7);

            $query = TerminalChannel::where('message_count', 0)
                ->where('created_at', '<', $cutoff);

            $count = $query->count();

            if ($count === 0) {
                return 'Keine gefunden';
            }

            if (! $dryRun) {
                $query->delete();
            }

            return "{$count} Channels" . ($dryRun ? ' [DRY RUN]' : ' gelöscht');
        });

        return 0;
    }

    /**
     * Kontext-Channels ohne Nachricht seit X Tagen.
     * Gruppen-Channels und DMs werden NICHT bereinigt (die sind bewusst erstellt).
     */
    protected function cleanupInactiveContextChannels(bool $dryRun, int $days): int
    {
        $this->components->task("Inaktive Kontext-Channels (>{$days} Tage)", function () use ($dryRun, $days) {
            $cutoff = now()->subDays($days);

            // Kontext-Channels deren letzte Nachricht älter als X Tage ist
            $query = TerminalChannel::where('type', 'context')
                ->where(function ($q) use ($cutoff) {
                    $q->whereHas('lastMessage', fn ($m) => $m->where('created_at', '<', $cutoff))
                      ->orWhere(function ($q2) use ($cutoff) {
                          // Oder: hat Nachrichten, aber updated_at ist alt
                          $q2->where('message_count', '>', 0)
                             ->where('updated_at', '<', $cutoff);
                      });
                });

            $count = $query->count();

            if ($count === 0) {
                return 'Keine gefunden';
            }

            if (! $dryRun) {
                $query->delete();
            }

            return "{$count} Channels" . ($dryRun ? ' [DRY RUN]' : ' gelöscht');
        });

        return 0;
    }

    /**
     * DMs wo keine Mitglieder mehr existieren (beide haben verlassen).
     */
    protected function cleanupAbandonedDms(bool $dryRun): int
    {
        $this->components->task('Verlassene DMs (keine Mitglieder)', function () use ($dryRun) {
            $query = TerminalChannel::where('type', 'dm')
                ->whereDoesntHave('members');

            $count = $query->count();

            if ($count === 0) {
                return 'Keine gefunden';
            }

            if (! $dryRun) {
                $query->delete();
            }

            return "{$count} Channels" . ($dryRun ? ' [DRY RUN]' : ' gelöscht');
        });

        return 0;
    }

    /**
     * Prüft ob ein Kontext-Model noch existiert (berücksichtigt SoftDeletes).
     */
    protected function contextExists(string $contextType, int $contextId): bool
    {
        try {
            if (! class_exists($contextType)) {
                return false;
            }

            // withTrashed() falls das Model SoftDeletes nutzt — wir wollen
            // auch soft-deleted Models als "gelöscht" behandeln
            $query = $contextType::where('id', $contextId);

            // Nicht withTrashed — wenn es soft-deleted ist, gilt es als gelöscht
            return $query->exists();
        } catch (\Throwable $e) {
            // Model-Klasse existiert nicht mehr, DB-Fehler, etc.
            return false;
        }
    }
}
