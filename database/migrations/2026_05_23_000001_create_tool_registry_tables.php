<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabellen werden nicht mehr benötigt — Registry ist In-Memory.
        // Nur Aufräumen, falls Reste aus früheren Deployments existieren.
        Schema::dropIfExists('tool_registry_requires');
        Schema::dropIfExists('tool_registry_tags');
        Schema::dropIfExists('tool_registry_entries');
    }

    public function down(): void
    {
        // Intentionally empty — tables are no longer used.
    }
};
