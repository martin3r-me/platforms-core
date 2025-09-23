<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_chat_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('core_chat_id')->index();
            $table->string('type'); // tool_call_start|tool_call_end|navigate|confirm|error
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_chat_events');
    }
};


