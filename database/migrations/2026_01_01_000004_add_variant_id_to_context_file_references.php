<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Spalte nur hinzufügen wenn sie nicht existiert
        if (!Schema::hasColumn('context_file_references', 'context_file_variant_id')) {
            Schema::table('context_file_references', function (Blueprint $table) {
                $table->foreignId('context_file_variant_id')
                    ->nullable()
                    ->after('context_file_id')
                    ->constrained('context_file_variants')
                    ->nullOnDelete();
            });
        }

        // 2. Neuen Unique Index ZUERST erstellen (wenn nicht existiert)
        if (!$this->indexExists('context_file_references', 'cfr_unique_file_variant_reference')) {
            Schema::table('context_file_references', function (Blueprint $table) {
                $table->unique(
                    ['context_file_id', 'context_file_variant_id', 'reference_type', 'reference_id'],
                    'cfr_unique_file_variant_reference'
                );
            });
        }

        // 3. Alten Unique Index DANACH löschen (wenn existiert)
        if ($this->indexExists('context_file_references', 'cfr_unique_reference')) {
            Schema::table('context_file_references', function (Blueprint $table) {
                $table->dropUnique('cfr_unique_reference');
            });
        }
    }

    public function down(): void
    {
        // Alten Index wiederherstellen (wenn nicht existiert)
        if (!$this->indexExists('context_file_references', 'cfr_unique_reference')) {
            Schema::table('context_file_references', function (Blueprint $table) {
                $table->unique(
                    ['context_file_id', 'reference_type', 'reference_id'],
                    'cfr_unique_reference'
                );
            });
        }

        // Neuen Index löschen (wenn existiert)
        if ($this->indexExists('context_file_references', 'cfr_unique_file_variant_reference')) {
            Schema::table('context_file_references', function (Blueprint $table) {
                $table->dropUnique('cfr_unique_file_variant_reference');
            });
        }

        // Spalte löschen (wenn existiert)
        if (Schema::hasColumn('context_file_references', 'context_file_variant_id')) {
            Schema::table('context_file_references', function (Blueprint $table) {
                $table->dropConstrainedForeignId('context_file_variant_id');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
