<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_messages', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('channel_id')->constrained('terminal_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('reply_count')->default(0);
            $table->timestamp('last_reply_at')->nullable();
            $table->text('body_html');
            $table->text('body_plain')->nullable();
            $table->string('type', 20)->default('message');
            $table->boolean('has_attachments')->default(false);
            $table->boolean('has_mentions')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('terminal_messages')
                ->nullOnDelete();

            $table->index(['channel_id', 'id'], 'terminal_messages_channel_cursor');
            $table->index(['parent_id'], 'terminal_messages_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_messages');
    }
};
