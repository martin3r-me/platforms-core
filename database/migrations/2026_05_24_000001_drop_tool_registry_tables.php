<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tool_registry_requires');
        Schema::dropIfExists('tool_registry_tags');
        Schema::dropIfExists('tool_registry_entries');
    }

    public function down(): void
    {
        // Tabellen werden nicht wiederhergestellt — Metadaten leben jetzt im Code.
    }
};
