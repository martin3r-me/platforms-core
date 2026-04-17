<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semantic_layers', function (Blueprint $table) {
            $table->dropUnique('semantic_layers_scope_unique');

            $table->string('label', 50)->default('leitbild')->after('scope_id');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('label');

            $table->unique(['scope_type', 'scope_id', 'label'], 'semantic_layers_scope_label_unique');
        });

        // Backfill existing rows
        DB::table('semantic_layers')
            ->whereNull('label')
            ->orWhere('label', '')
            ->update(['label' => 'leitbild', 'sort_order' => 0]);
    }

    public function down(): void
    {
        Schema::table('semantic_layers', function (Blueprint $table) {
            $table->dropUnique('semantic_layers_scope_label_unique');
            $table->dropColumn(['label', 'sort_order']);
            $table->unique(['scope_type', 'scope_id'], 'semantic_layers_scope_unique');
        });
    }
};
