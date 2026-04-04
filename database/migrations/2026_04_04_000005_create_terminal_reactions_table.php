<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('terminal_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 50);
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji'], 'terminal_reactions_unique');
            $table->index(['message_id', 'emoji'], 'terminal_reactions_message_emoji');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_reactions');
    }
};
