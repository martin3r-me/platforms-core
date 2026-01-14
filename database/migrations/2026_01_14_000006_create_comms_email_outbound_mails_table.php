<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_email_outbound_mails', function (Blueprint $table) {
            $table->id();

            $table->foreignId('thread_id')->constrained('comms_email_threads')->cascadeOnDelete();
            $table->foreignId('comms_channel_id')->constrained('comms_channels')->cascadeOnDelete();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('from')->nullable();
            $table->text('to')->nullable();
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->text('reply_to')->nullable();
            $table->text('subject')->nullable();

            $table->longText('html_body')->nullable();
            $table->longText('text_body')->nullable();

            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['thread_id', 'sent_at'], 'comms_email_outbound_mails_thread_sent_index');
            $table->index(['comms_channel_id', 'sent_at'], 'comms_email_outbound_mails_channel_sent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_email_outbound_mails');
    }
};

