<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\UuidV7;

return new class extends Migration
{
    /**
     * Canvas-Tabellen, die contextable_type/contextable_id haben.
     * Morph-Alias → Tabelle
     */
    private array $canvasTables = [
        'canvas' => 'canvases',
        'bmc_canvas' => 'bmc_canvases',
        'pc_canvas' => 'pc_canvases',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('core_entity_links')) {
            return;
        }

        foreach ($this->canvasTables as $morphAlias => $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'contextable_type') || !Schema::hasColumn($tableName, 'contextable_id')) {
                continue;
            }

            $rows = DB::table($tableName)
                ->whereNotNull('contextable_type')
                ->whereNotNull('contextable_id')
                ->where('contextable_type', '!=', '')
                ->select(['id', 'team_id', 'contextable_type', 'contextable_id', 'created_by_user_id'])
                ->get();

            foreach ($rows as $row) {
                $teamId = $row->team_id;
                if (!$teamId) {
                    continue;
                }

                // contextable_type kann ein Morph-Alias oder FQCN sein
                $contextType = $row->contextable_type;
                $contextId = $row->contextable_id;

                // Duplikat-Check (idempotent)
                $exists = DB::table('core_entity_links')
                    ->where('team_id', $teamId)
                    ->where('source_type', $contextType)
                    ->where('source_id', $contextId)
                    ->where('target_type', $morphAlias)
                    ->where('target_id', $row->id)
                    ->where('link_type', 'context')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('core_entity_links')->insert([
                    'uuid' => UuidV7::generate(),
                    'team_id' => $teamId,
                    'source_type' => $contextType,
                    'source_id' => $contextId,
                    'target_type' => $morphAlias,
                    'target_id' => $row->id,
                    'link_type' => 'context',
                    'created_by_user_id' => $row->created_by_user_id ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Entferne nur die migrierten context-Links, nicht alle
        DB::table('core_entity_links')
            ->where('link_type', 'context')
            ->whereIn('target_type', array_keys($this->canvasTables))
            ->delete();
    }
};
