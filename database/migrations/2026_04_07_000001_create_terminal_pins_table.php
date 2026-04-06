<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('terminal_channels')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('terminal_messages')->cascadeOnDelete();
            $table->foreignId('pinned_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['channel_id', 'message_id']);
            $table->index(['channel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_pins');
    }
};
