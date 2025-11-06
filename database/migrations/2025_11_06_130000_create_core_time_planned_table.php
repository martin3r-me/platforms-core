<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_time_planned', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('context_type');
            $table->unsignedBigInteger('context_id');

            $table->unsignedInteger('planned_minutes');
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['context_type', 'context_id', 'is_active'], 'core_time_planned_context_active_index');
            $table->index(['context_type', 'context_id'], 'core_time_planned_context_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_time_planned');
    }
};

