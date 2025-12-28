<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checkin_todos', function (Blueprint $table) {
            // title zu TEXT ändern (für verschlüsselte Werte) und Hash-Spalte hinzufügen
            $table->text('title')->change();
            $table->char('title_hash', 64)->nullable()->after('title');
            $table->index('title_hash', 'idx_title_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkin_todos', function (Blueprint $table) {
            $table->dropIndex('idx_title_hash');
            $table->dropColumn('title_hash');
            $table->string('title')->change();
        });
    }
};

