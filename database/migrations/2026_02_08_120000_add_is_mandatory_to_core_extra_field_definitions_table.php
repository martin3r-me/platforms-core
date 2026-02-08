<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_extra_field_definitions', function (Blueprint $table) {
            $table->boolean('is_mandatory')->default(false)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('core_extra_field_definitions', function (Blueprint $table) {
            $table->dropColumn('is_mandatory');
        });
    }
};
