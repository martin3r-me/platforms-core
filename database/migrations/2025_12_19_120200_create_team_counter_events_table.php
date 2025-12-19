<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_counter_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_counter_definition_id')
                ->constrained('team_counter_definitions')
                ->cascadeOnDelete();

            // Team-Kontext, in dem gezählt wurde (Kind-Team oder Root-Team)
            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->integer('delta')->default(1);
            $table->date('occurred_on')->index();
            $table->timestamp('occurred_at')->useCurrent()->index();

            $table->timestamps();

            $table->index(['team_counter_definition_id', 'occurred_on'], 'team_counter_events_def_on_idx');
            $table->index(['team_id', 'occurred_on'], 'team_counter_events_team_on_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_counter_events');
    }
};


