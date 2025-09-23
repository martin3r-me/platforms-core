<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('core_chat_id')->index();
            $table->string('role', 20); // user|assistant|tool|system
            $table->longText('content')->nullable();
            $table->json('meta')->nullable(); // z.B. intent, slots, impact, confidence
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_chat_messages');
    }
};


