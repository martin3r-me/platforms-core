<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comms_whatsapp_threads')) {
            return;
        }

        Schema::create('comms_whatsapp_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('comms_channel_id')->constrained('comms_channels')->cascadeOnDelete();
            $table->string('token', 32)->unique();  // ULID for webhook matching
            $table->string('remote_phone_number', 32);  // E.164 format
            $table->string('contact_type')->nullable();  // Polymorphic contact
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('context_model')->nullable();  // Context attachment (e.g., Applicant)
            $table->unsignedBigInteger('context_model_id')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->boolean('is_unread')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['comms_channel_id', 'remote_phone_number'], 'wa_threads_channel_phone_unique');
            $table->index(['team_id', 'is_unread']);
            $table->index(['context_model', 'context_model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_whatsapp_threads');
    }
};
