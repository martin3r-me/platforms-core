<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_whatsapp_conversation_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('comms_whatsapp_thread_id')
                ->constrained('comms_whatsapp_threads')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->string('label', 255);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable(); // null = active
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            // Only one active conversation thread per WhatsApp thread (ended_at IS NULL)
            // Enforced in application logic since MySQL doesn't support partial unique indexes
            $table->index(['comms_whatsapp_thread_id', 'ended_at'], 'wa_conv_thread_active_idx');
            $table->index(['team_id', 'created_at'], 'wa_conv_thread_team_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_whatsapp_conversation_threads');
    }
};
