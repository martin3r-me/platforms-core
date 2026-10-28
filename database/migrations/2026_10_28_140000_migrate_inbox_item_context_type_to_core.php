<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zieht die gebuchte Inbox-Zeit auf die neue Core-Klasse um.
 *
 * Die Inbox-Engine ist aus dem inbox-Modul nach Core gewandert; das InboxItem
 * heißt jetzt `Platform\Core\Inbox\Models\InboxItem` statt `Platform\Inbox\Models\InboxItem`.
 *
 * `organization_time_entries.context_type` (und die Soll-/Perioden-Tabellen)
 * speichern den vollqualifizierten Klassennamen LITERAL — gesetzt vom
 * MaterializeMeetingTimeCommand. EntityTimeResolver baut die Ist-Zeit-Query über
 * `where('context_type', $fqcn)`, wobei $fqcn aus dem (jetzt auf Core zeigenden)
 * Cascade-Provider kommt. Ohne diese Umschreibung matcht die Query die alten
 * Zeilen nicht mehr → die an Knoten gebuchte Meeting-Ist-Zeit verschwände still.
 *
 * `dimension_links` brauchen nichts: die speichern den stabilen Morph-Alias
 * `inbox_item`, nicht den FQCN.
 *
 * Idempotent (WHERE auf den alten Wert) und reversibel.
 */
return new class extends Migration
{
    private const OLD = 'Platform\\Inbox\\Models\\InboxItem';
    private const NEW = 'Platform\\Core\\Inbox\\Models\\InboxItem';

    private const TABLES = [
        'organization_time_entries',
        'organization_time_planned',
        'organization_time_periods',
    ];

    public function up(): void
    {
        $this->remap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->remap(self::NEW, self::OLD);
    }

    private function remap(string $from, string $to): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'context_type')) {
                continue;
            }

            DB::table($table)
                ->where('context_type', $from)
                ->update(['context_type' => $to]);
        }
    }
};
