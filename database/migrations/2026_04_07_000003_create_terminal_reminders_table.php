<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('terminal_messages')->cascadeOnDelete();
            $table->timestamp('remind_at');
            $table->boolean('reminded')->default(false);
            $table->timestamps();

            $table->index(['reminded', 'remind_at']);
            $table->index(['user_id', 'reminded']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_reminders');
    }
};
