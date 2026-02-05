<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_channel_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comms_channel_id')->constrained('comms_channels')->cascadeOnDelete();
            $table->string('context_model');
            $table->unsignedBigInteger('context_model_id');
            $table->timestamps();

            $table->unique(['comms_channel_id', 'context_model', 'context_model_id'], 'ccc_channel_context_unique');
            $table->index(['context_model', 'context_model_id'], 'ccc_context_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_channel_contexts');
    }
};
