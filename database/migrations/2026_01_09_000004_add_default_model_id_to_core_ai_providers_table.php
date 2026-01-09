<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_ai_providers', function (Blueprint $table) {
            $table->foreignId('default_model_id')
                ->nullable()
                ->after('catalog_url')
                ->constrained('core_ai_models')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('core_ai_providers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_model_id');
        });
    }
};


