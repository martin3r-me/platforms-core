<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'comms_whatsapp_conversation_threads';

        // Drop old foreign keys with too-long names (if they exist)
        $this->dropForeignKeyIfExists($table, 'comms_whatsapp_conversation_threads_comms_whatsapp_thread_id_foreign');
        $this->dropForeignKeyIfExists($table, 'comms_whatsapp_conversation_threads_created_by_user_id_foreign');

        // Add foreign keys with shorter names (if they don't already exist)
        $this->addForeignKeyIfNotExists(
            $table,
            'wa_conv_threads_thread_id_fk',
            'comms_whatsapp_thread_id',
            'comms_whatsapp_threads',
            'id',
            'CASCADE'
        );

        $this->addForeignKeyIfNotExists(
            $table,
            'wa_conv_threads_created_by_fk',
            'created_by_user_id',
            'users',
            'id',
            'SET NULL'
        );
    }

    public function down(): void
    {
        // Cannot rollback to the old long FK names (> 64 chars)
        // Just drop the short-named FKs - the create migration's down() will handle the rest
        $this->dropForeignKeyIfExists('comms_whatsapp_conversation_threads', 'wa_conv_threads_thread_id_fk');
        $this->dropForeignKeyIfExists('comms_whatsapp_conversation_threads', 'wa_conv_threads_created_by_fk');
    }

    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        if ($this->foreignKeyExists($table, $foreignKey)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($foreignKey));
        }
    }

    private function addForeignKeyIfNotExists(
        string $table,
        string $constraintName,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete
    ): void {
        if (! $this->foreignKeyExists($table, $constraintName)) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`) ON DELETE %s',
                $table,
                $constraintName,
                $column,
                $referencedTable,
                $referencedColumn,
                $onDelete
            ));
        }
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $database = DB::getDatabaseName();

        return (bool) DB::selectOne(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$database, $table, $foreignKey]
        );
    }
};
