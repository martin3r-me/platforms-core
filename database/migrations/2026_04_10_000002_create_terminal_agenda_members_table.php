<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_agenda_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_id')->constrained('terminal_agendas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamps();

            $table->unique(['agenda_id', 'user_id'], 'terminal_agenda_members_agenda_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_agenda_members');
    }
};
