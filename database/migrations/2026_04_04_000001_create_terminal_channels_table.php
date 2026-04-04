<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_channels', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('type', 20); // channel, context, dm
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('context_type', 128)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('participant_hash', 64)->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'type'], 'terminal_channels_team_type');
            $table->index(['team_id', 'context_type', 'context_id'], 'terminal_channels_team_context');
            $table->index(['team_id', 'participant_hash'], 'terminal_channels_team_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_channels');
    }
};
