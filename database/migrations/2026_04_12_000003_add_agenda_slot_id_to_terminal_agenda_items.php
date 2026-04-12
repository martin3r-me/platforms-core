<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminal_agenda_items', function (Blueprint $table) {
            $table->foreignId('agenda_slot_id')->nullable()->after('agenda_id')->constrained('terminal_agenda_slots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('terminal_agenda_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agenda_slot_id');
        });
    }
};
