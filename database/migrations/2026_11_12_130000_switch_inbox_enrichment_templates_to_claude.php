<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inbox-Anreicherung läuft über die Claude-API statt OpenAI: Templates mit
 * "openai:…" auf Claude Haiku umstellen, Spalten-Default mitziehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inbox_enrichment_templates')) {
            return;
        }

        DB::table('inbox_enrichment_templates')
            ->where('preferred_provider', 'like', 'openai:%')
            ->update(['preferred_provider' => 'claude:claude-haiku-4-5']);

        Schema::table('inbox_enrichment_templates', function (Blueprint $table) {
            $table->string('preferred_provider', 100)->default('claude:claude-haiku-4-5')->change();
        });
    }

    public function down(): void
    {
        // Bewusst nicht zurück auf OpenAI.
    }
};
