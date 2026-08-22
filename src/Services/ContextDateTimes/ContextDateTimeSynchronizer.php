<?php

namespace Platform\Core\Services\ContextDateTimes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Platform\Core\Enums\ContextDateTimeKind;
use Platform\Core\Models\CoreContextDateTime;

/**
 * Spiegelt Datums-Spalten eines beliebigen Models in die generische Tabelle
 * core_context_date_times (Dual-Write) und hält deren Occurrence-Schatten aktuell.
 *
 * Die Ziel-Row wird über (context_type, context_id, source) identifiziert, wobei
 * source = "migrated_from:<table>.<column>". Dadurch ist {@see sync()} idempotent
 * und beliebig oft re-runbar: gleicher Quell-Zustand → keine Änderung.
 *
 * Genutzt von:
 *   - {@see \Platform\Core\Observers\ContextDateTimeObserver} (Live-Dual-Write)
 *   - {@see \Platform\Core\Console\Commands\BackfillContextDateTimesCommand} (Backfill)
 */
class ContextDateTimeSynchronizer
{
    /**
     * Spiegelt alle im Field-Map beschriebenen Spalten des Models.
     *
     * @param  array<string, mixed>  $map      Spalte => ContextDateTimeKind|string|array{kind,label?}
     * @param  bool                  $persist  false = Dry-Run (nur Klassifikation, kein Write)
     * @return array{created:int, updated:int, deleted:int, unchanged:int, skipped:int}
     */
    public function sync(Model $model, array $map, bool $persist = true): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0, 'skipped' => 0];

        if (empty($map)) {
            return $counts;
        }

        $contextType = $model->getMorphClass();
        $contextId = $model->getKey();
        $teamId = $model->getAttribute('team_id');
        $table = $model->getTable();

        foreach ($map as $field => $config) {
            [$kind, $label] = $this->parseConfig($config);
            if ($kind === null) {
                $counts['skipped']++;
                continue;
            }

            $source = 'migrated_from:'.$table.'.'.$field;

            $existing = CoreContextDateTime::withTrashed()
                ->where('context_type', $contextType)
                ->where('context_id', $contextId)
                ->where('source', $source)
                ->first();

            $value = $this->normalizeDateTime($model->getAttribute($field));

            // Quelle leer → Mirror soft-löschen (falls vorhanden & noch aktiv).
            if ($value === null) {
                if ($existing && $existing->deleted_at === null) {
                    if ($persist) {
                        $existing->delete();
                    }
                    $counts['deleted']++;
                } else {
                    $counts['unchanged']++;
                }
                continue;
            }

            // team_id ist NOT NULL – ohne Team lässt sich keine Row schreiben.
            if ($teamId === null) {
                $counts['skipped']++;
                continue;
            }

            $attrs = [
                'kind' => $kind,
                'label' => $label,
                'starts_at' => $value,
                'ends_at' => null,
                'team_id' => $teamId,
            ];

            if ($existing) {
                $wasTrashed = $existing->deleted_at !== null;
                $changed = $wasTrashed
                    || $existing->starts_at === null
                    || ! $existing->starts_at->equalTo($value)
                    || ($existing->kind?->value) !== $kind
                    || $existing->label !== $label;

                if ($changed) {
                    if ($persist) {
                        if ($wasTrashed) {
                            $existing->restore();
                        }
                        $existing->fill($attrs)->save();
                        $this->syncOccurrence($existing, $value);
                    }
                    $counts['updated']++;
                } else {
                    $counts['unchanged']++;
                }

                continue;
            }

            if ($persist) {
                $row = CoreContextDateTime::create($attrs + [
                    'context_type' => $contextType,
                    'context_id' => $contextId,
                    'source' => $source,
                ]);
                $this->syncOccurrence($row, $value);
            }
            $counts['created']++;
        }

        return $counts;
    }

    /**
     * Entfernt ALLE CoreContextDateTime-Einträge eines Kontexts – unabhängig von
     * ihrer Quelle (Dual-Write-Mirror wie manuell/generisch angehängte Einträge).
     *
     * Wird beim Löschen des Host-Records aufgerufen: ein gelöschter Host darf
     * keine verwaisten CoreContextDateTime/Occurrence-Rows hinterlassen
     * (Lesson Learned aus Issue #147 – unvalidierte/ungepflegte polymorphe
     * Referenzen sind ein wiederkehrender Fehlerherd).
     *
     * @param  bool  $force  true = forceDelete (Hard-Delete der Quelle), sonst Soft-Delete
     * @return array{deleted:int}
     */
    public function purge(Model $model, bool $force = false): array
    {
        $counts = ['deleted' => 0];

        $rows = CoreContextDateTime::withTrashed()
            ->where('context_type', $model->getMorphClass())
            ->where('context_id', $model->getKey())
            ->get();

        foreach ($rows as $row) {
            if ($force) {
                $row->occurrences()->delete();
                $row->forceDelete();
                $counts['deleted']++;
            } elseif ($row->deleted_at === null) {
                $row->delete();
                $counts['deleted']++;
            }
        }

        return $counts;
    }

    /**
     * Zerlegt einen Map-Eintrag in [kind-value, label].
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function parseConfig(mixed $config): array
    {
        if ($config instanceof ContextDateTimeKind) {
            return [$config->value, null];
        }

        if (is_string($config)) {
            return [$config, null];
        }

        if (is_array($config)) {
            $kind = $config['kind'] ?? null;
            if ($kind instanceof ContextDateTimeKind) {
                $kind = $kind->value;
            }

            return [is_string($kind) ? $kind : null, $config['label'] ?? null];
        }

        return [null, null];
    }

    /**
     * Wandelt einen Roh-Wert (Carbon, DateTime, String, leer) in Carbon|null.
     */
    protected function normalizeDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Hält den Occurrence-Schatten eines nicht-wiederkehrenden Zeitpunkts aktuell:
     * genau eine Occurrence, die starts_at/ends_at spiegelt. RRULE-Expansion ist
     * nicht Aufgabe des Dual-Write und wird daher übersprungen.
     */
    protected function syncOccurrence(CoreContextDateTime $dateTime, Carbon $startsAt): void
    {
        if ($dateTime->isRecurring()) {
            return;
        }

        $dateTime->occurrences()->delete();
        $dateTime->occurrences()->create([
            'starts_at' => $startsAt,
            'ends_at' => $dateTime->ends_at,
            'is_exception' => false,
        ]);
    }
}
