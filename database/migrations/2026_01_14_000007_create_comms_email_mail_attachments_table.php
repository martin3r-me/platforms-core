<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_email_mail_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inbound_mail_id')
                ->nullable()
                ->constrained('comms_email_inbound_mails')
                ->cascadeOnDelete();

            $table->foreignId('outbound_mail_id')
                ->nullable()
                ->constrained('comms_email_outbound_mails')
                ->cascadeOnDelete();

            $table->string('filename')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->string('disk', 64)->default('emails');
            $table->text('path')->nullable();

            $table->string('cid')->nullable();
            $table->boolean('inline')->default(false);

            $table->timestamps();

            $table->index(['inbound_mail_id'], 'comms_email_mail_attachments_inbound_index');
            $table->index(['outbound_mail_id'], 'comms_email_mail_attachments_outbound_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_email_mail_attachments');
    }
};

