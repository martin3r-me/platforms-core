<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_email_threads', function (Blueprint $table) {
            $table->id();

            // Root team scope (same pattern as connections/channels)
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->foreignId('comms_channel_id')->constrained('comms_channels')->cascadeOnDelete();

            // Conversation token (ULID base32, 26 chars)
            $table->string('token', 32);

            $table->string('subject')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['comms_channel_id', 'token'], 'comms_email_threads_channel_token_unique');
            $table->index(['team_id', 'comms_channel_id'], 'comms_email_threads_team_channel_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_email_threads');
    }
};

