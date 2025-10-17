<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('modulables', function (Blueprint $table) {
            $table->foreignId('team_id')->after('module_id')->nullable(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('modulables', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }
};


