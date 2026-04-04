<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_channel_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('terminal_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->string('notification_preference', 20)->default('all');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'user_id'], 'terminal_members_channel_user');
            $table->index(['user_id', 'last_read_message_id'], 'terminal_members_user_cursor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_channel_members');
    }
};
