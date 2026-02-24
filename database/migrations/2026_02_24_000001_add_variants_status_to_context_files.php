<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('context_files', function (Blueprint $table) {
            $table->string('variants_status', 20)->default('complete')->after('meta');
        });

        // Bestehende Nicht-Bild-Rows auf 'none' setzen
        DB::table('context_files')
            ->where('mime_type', 'not like', 'image/%')
            ->update(['variants_status' => 'none']);

        // Bestehende Bild-Rows ohne Varianten auf 'none' setzen
        DB::table('context_files')
            ->where('mime_type', 'like', 'image/%')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('context_file_variants')
                    ->whereColumn('context_file_variants.context_file_id', 'context_files.id');
            })
            ->update(['variants_status' => 'none']);
    }

    public function down(): void
    {
        Schema::table('context_files', function (Blueprint $table) {
            $table->dropColumn('variants_status');
        });
    }
};
