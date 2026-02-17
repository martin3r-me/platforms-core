<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comms_whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('conversation_thread_id')
                ->nullable()
                ->after('comms_whatsapp_thread_id')
                ->constrained('comms_whatsapp_conversation_threads')
                ->nullOnDelete();

            $table->index('conversation_thread_id', 'wa_messages_conv_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comms_whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_thread_id']);
            $table->dropIndex('wa_messages_conv_thread_idx');
            $table->dropColumn('conversation_thread_id');
        });
    }
};
