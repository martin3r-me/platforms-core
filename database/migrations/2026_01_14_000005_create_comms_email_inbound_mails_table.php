<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_email_inbound_mails', function (Blueprint $table) {
            $table->id();

            $table->foreignId('thread_id')->constrained('comms_email_threads')->cascadeOnDelete();

            $table->string('postmark_id', 128)->nullable();

            $table->text('from')->nullable();
            $table->text('to')->nullable();
            $table->text('cc')->nullable();
            $table->text('reply_to')->nullable();
            $table->text('subject')->nullable();

            $table->longText('html_body')->nullable();
            $table->longText('text_body')->nullable();

            $table->json('headers')->nullable();
            $table->json('attachments_payload')->nullable();

            $table->decimal('spam_score', 6, 3)->nullable();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            $table->unique(['postmark_id'], 'comms_email_inbound_mails_postmark_id_unique');
            $table->index(['thread_id', 'received_at'], 'comms_email_inbound_mails_thread_received_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_email_inbound_mails');
    }
};

