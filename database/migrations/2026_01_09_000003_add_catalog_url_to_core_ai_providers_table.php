<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_ai_providers', function (Blueprint $table) {
            // Optional: external/internal catalog endpoint that returns model details (pricing, limits, features).
            // This is the provider-agnostic way to fetch pricing "per API".
            $table->string('catalog_url', 512)->nullable()->after('base_url');
        });
    }

    public function down(): void
    {
        Schema::table('core_ai_providers', function (Blueprint $table) {
            $table->dropColumn('catalog_url');
        });
    }
};


