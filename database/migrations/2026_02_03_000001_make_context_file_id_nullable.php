<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Drop FK on context_file_id FIRST (this one uses the unique index!)
        $fkNamesFileId = $this->getForeignKeyNames('context_file_references', 'context_file_id');
        foreach ($fkNamesFileId as $fkName) {
            DB::statement("ALTER TABLE context_file_references DROP FOREIGN KEY `{$fkName}`");
        }

        // Step 2: Also drop any FK on context_file_variant_id (if exists)
        $fkNamesVariant = $this->getForeignKeyNames('context_file_references', 'context_file_variant_id');
        foreach ($fkNamesVariant as $fkName) {
            DB::statement("ALTER TABLE context_file_references DROP FOREIGN KEY `{$fkName}`");
        }

        // Step 3: Now we can safely drop the unique index
        if ($this->indexExists('context_file_references', 'cfr_unique_file_variant_reference')) {
            Schema::table('context_file_references', function (Blueprint $table) {
                $table->dropUnique('cfr_unique_file_variant_reference');
            });
        }

        // Step 4: Make context_file_id nullable
        Schema::table('context_file_references', function (Blueprint $table) {
            $table->unsignedBigInteger('context_file_id')->nullable()->change();
        });

        // Step 5: Re-add FK on context_file_id (nullable, SET NULL on delete)
        Schema::table('context_file_references', function (Blueprint $table) {
            $table->foreign('context_file_id')
                ->references('id')
                ->on('context_files')
                ->nullOnDelete();
        });

        // Step 6: Re-add FK on context_file_variant_id
        Schema::table('context_file_references', function (Blueprint $table) {
            $table->foreign('context_file_variant_id')
                ->references('id')
                ->on('context_file_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Drop both FKs
        $fkNamesFileId = $this->getForeignKeyNames('context_file_references', 'context_file_id');
        foreach ($fkNamesFileId as $fkName) {
            DB::statement("ALTER TABLE context_file_references DROP FOREIGN KEY `{$fkName}`");
        }

        $fkNamesVariant = $this->getForeignKeyNames('context_file_references', 'context_file_variant_id');
        foreach ($fkNamesVariant as $fkName) {
            DB::statement("ALTER TABLE context_file_references DROP FOREIGN KEY `{$fkName}`");
        }

        // Make context_file_id NOT NULL again
        Schema::table('context_file_references', function (Blueprint $table) {
            $table->unsignedBigInteger('context_file_id')->nullable(false)->change();
        });

        // Re-add unique constraint
        if (!$this->indexExists('context_file_references', 'cfr_unique_file_variant_reference')) {
            Schema::table('context_file_references', function (Blueprint $table) {
                $table->unique(
                    ['context_file_id', 'context_file_variant_id', 'reference_type', 'reference_id'],
                    'cfr_unique_file_variant_reference'
                );
            });
        }

        // Re-add FKs
        Schema::table('context_file_references', function (Blueprint $table) {
            $table->foreign('context_file_id')
                ->references('id')
                ->on('context_files')
                ->cascadeOnDelete();
        });

        Schema::table('context_file_references', function (Blueprint $table) {
            $table->foreign('context_file_variant_id')
                ->references('id')
                ->on('context_file_variants')
                ->nullOnDelete();
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }

    private function getForeignKeyNames(string $table, string $column): array
    {
        $database = DB::getDatabaseName();
        $results = DB::select("
            SELECT tc.CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS tc
            JOIN information_schema.KEY_COLUMN_USAGE kcu
                ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                AND tc.TABLE_NAME = kcu.TABLE_NAME
            WHERE tc.TABLE_SCHEMA = ?
              AND tc.TABLE_NAME = ?
              AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'
              AND kcu.COLUMN_NAME = ?
        ", [$database, $table, $column]);

        return array_map(fn($r) => $r->CONSTRAINT_NAME, $results);
    }
};
