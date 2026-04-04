<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('terminal_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('terminal_channels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['message_id', 'user_id'], 'terminal_mentions_message_user');
            $table->index(['user_id', 'created_at'], 'terminal_mentions_user_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_mentions');
    }
};
