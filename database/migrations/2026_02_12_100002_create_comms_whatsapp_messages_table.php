<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comms_whatsapp_messages')) {
            return;
        }

        Schema::create('comms_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comms_whatsapp_thread_id')->constrained('comms_whatsapp_threads')->cascadeOnDelete();
            $table->string('direction', 16);  // 'inbound' | 'outbound'
            $table->string('meta_message_id', 128)->nullable()->unique();  // WhatsApp message ID from Meta
            $table->text('body')->nullable();
            $table->string('message_type', 32)->default('text');  // text, image, document, audio, video, template
            $table->string('template_name')->nullable();
            $table->json('template_params')->nullable();
            $table->string('status', 32)->default('pending');  // pending, sent, delivered, read, failed
            $table->timestamp('status_updated_at')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('meta_payload')->nullable();  // Raw Meta API response/payload
            $table->timestamps();

            $table->index(['comms_whatsapp_thread_id', 'created_at'], 'wa_messages_thread_created_index');
            $table->index('direction');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_whatsapp_messages');
    }
};
