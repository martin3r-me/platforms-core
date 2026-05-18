<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * MCP-Tool: Listet stale (ausgeblendete) Records aller Module.
 *
 * Scannt automatisch alle Tabellen mit `last_viewed_at` Spalte.
 * Kann einzelne Records reaktivieren (last_viewed_at auf now() setzen).
 */
class ListStaleRecordsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.stale.GET';
    }

    public function getDescription(): string
    {
        return 'GET /stale - Listet alle stale (ausgeblendeten) Records. Kann Records reaktivieren. Parameter: table (optional), team_id (optional), reactivate_table + reactivate_id (optional, um einen Record zu reaktivieren).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => 'Optional: Nur eine bestimmte Tabelle anzeigen (z.B. "planner_projects", "helpdesk_tickets").',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'reactivate_table' => [
                    'type' => 'string',
                    'description' => 'Tabelle des Records der reaktiviert werden soll.',
                ],
                'reactivate_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Records der reaktiviert werden soll (setzt last_viewed_at auf jetzt).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max. Anzahl Records pro Tabelle. Default: 20.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // Reaktivierung?
            if (!empty($arguments['reactivate_table']) && !empty($arguments['reactivate_id'])) {
                return $this->reactivateRecord(
                    $arguments['reactivate_table'],
                    (int) $arguments['reactivate_id'],
                    $context
                );
            }

            // Team-ID bestimmen
            $teamId = $arguments['team_id'] ?? $context->team?->id;
            $filterTable = $arguments['table'] ?? null;
            $limit = min((int) ($arguments['limit'] ?? 20), 100);

            $tables = $this->findTablesWithLastViewedAt();

            if ($filterTable) {
                $tables = in_array($filterTable, $tables) ? [$filterTable] : [];
                if (empty($tables)) {
                    return ToolResult::error('NOT_FOUND', "Tabelle '{$filterTable}' hat keine last_viewed_at Spalte.");
                }
            }

            $result = [];
            $totalStale = 0;

            foreach ($tables as $table) {
                $query = DB::table($table)
                    ->whereNotNull('last_viewed_at');

                // Soft-deleted Records ausschliessen
                if (Schema::hasColumn($table, 'deleted_at')) {
                    $query->whereNull('deleted_at');
                }

                // Team-Filter wenn Spalte vorhanden
                if ($teamId && Schema::hasColumn($table, 'team_id')) {
                    $query->where('team_id', $teamId);
                }

                // Stale = last_viewed_at < jetzt (wir wissen den Threshold nicht pro Tabelle,
                // also zeigen wir alles was potentiell stale ist - der Scope entscheidet in der App)
                // Wir nutzen den konservativsten Threshold (90 Tage)
                $threshold = Carbon::now()->subDays(90);
                $query->where('last_viewed_at', '<', $threshold);

                $count = (clone $query)->count();

                if ($count === 0) {
                    continue;
                }

                $totalStale += $count;

                // Name-Spalte finden fuer die Anzeige
                $nameColumn = $this->findNameColumn($table);

                $records = $query
                    ->select(array_filter([
                        'id',
                        $nameColumn,
                        'last_viewed_at',
                        Schema::hasColumn($table, 'team_id') ? 'team_id' : null,
                    ]))
                    ->orderBy('last_viewed_at', 'asc')
                    ->limit($limit)
                    ->get()
                    ->map(function ($row) use ($nameColumn) {
                        $data = [
                            'id' => $row->id,
                            'last_viewed_at' => $row->last_viewed_at,
                        ];
                        if ($nameColumn && isset($row->{$nameColumn})) {
                            $data['name'] = $row->{$nameColumn};
                        }
                        if (isset($row->team_id)) {
                            $data['team_id'] = $row->team_id;
                        }
                        return $data;
                    })
                    ->toArray();

                $hasSoftDeletes = Schema::hasColumn($table, 'deleted_at');

                $result[] = [
                    'table' => $table,
                    'stale_count' => $count,
                    'shown' => count($records),
                    'has_soft_deletes' => $hasSoftDeletes,
                    'purge_eligible' => $hasSoftDeletes,
                    'records' => $records,
                ];
            }

            return ToolResult::success([
                'total_stale' => $totalStale,
                'tables' => $result,
                'team_id' => $teamId,
                'hint' => $totalStale > 0
                    ? 'Nutze reactivate_table + reactivate_id um einen Record zu reaktivieren.'
                    : 'Keine stale Records gefunden.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    private function reactivateRecord(string $table, int $id, ToolContext $context): ToolResult
    {
        $tables = $this->findTablesWithLastViewedAt();

        if (!in_array($table, $tables)) {
            return ToolResult::error('NOT_FOUND', "Tabelle '{$table}' hat keine last_viewed_at Spalte.");
        }

        $record = DB::table($table)->find($id);

        if (!$record) {
            return ToolResult::error('NOT_FOUND', "Record {$id} in {$table} nicht gefunden.");
        }

        DB::table($table)
            ->where('id', $id)
            ->update(['last_viewed_at' => Carbon::now()]);

        $nameColumn = $this->findNameColumn($table);
        $name = $nameColumn && isset($record->{$nameColumn}) ? $record->{$nameColumn} : "ID {$id}";

        return ToolResult::success([
            'reactivated' => true,
            'table' => $table,
            'id' => $id,
            'name' => $name,
            'message' => "Record '{$name}' in {$table} wurde reaktiviert (last_viewed_at auf jetzt gesetzt).",
        ]);
    }

    /**
     * Findet alle Tabellen mit last_viewed_at Spalte.
     */
    private function findTablesWithLastViewedAt(): array
    {
        $allTables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->toArray();

        $tables = [];
        foreach ($allTables as $table) {
            if (Schema::hasColumn($table, 'last_viewed_at')) {
                $tables[] = $table;
            }
        }

        sort($tables);
        return $tables;
    }

    /**
     * Findet die passende Name-Spalte fuer eine Tabelle.
     */
    private function findNameColumn(string $table): ?string
    {
        foreach (['name', 'title', 'subject'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'query',
            'tags' => ['core', 'stale', 'cleanup'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => false,
            'idempotent' => true,
        ];
    }
}
