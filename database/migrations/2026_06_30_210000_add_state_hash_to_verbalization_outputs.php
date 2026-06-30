<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verbalization_outputs', function (Blueprint $table) {
            // Hash ueber Subject-Inhalt (Identity + Facts + Edges, OHNE Freshness).
            // Beim Refresh wird dieser Hash mit dem juengsten Output verglichen —
            // gleicher Hash = kein inhaltlicher Delta = keinen neuen Output erzeugen.
            $table->string('state_hash', 64)->nullable()->after('output_tokens')->index('voutp_state_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('verbalization_outputs', function (Blueprint $table) {
            $table->dropIndex('voutp_state_hash_idx');
            $table->dropColumn('state_hash');
        });
    }
};
