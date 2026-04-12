<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_id')->constrained('terminal_agendas')->cascadeOnDelete();
            $table->string('agendable_type', 128)->nullable();
            $table->unsignedBigInteger('agendable_id')->nullable();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('date')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->boolean('is_done')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('color', 20)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agenda_id', 'date'], 'terminal_agenda_items_agenda_date');
            $table->index(['agendable_type', 'agendable_id'], 'terminal_agenda_items_agendable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_agenda_items');
    }
};
