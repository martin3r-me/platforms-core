<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('core_chat_messages') && !Schema::hasColumn('core_chat_messages', 'thread_id')) {
            Schema::table('core_chat_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('thread_id')->nullable()->after('core_chat_id')->index();
            });
        }
        if (Schema::hasTable('core_chat_events') && !Schema::hasColumn('core_chat_events', 'thread_id')) {
            Schema::table('core_chat_events', function (Blueprint $table) {
                $table->unsignedBigInteger('thread_id')->nullable()->after('core_chat_id')->index();
            });
        }
        if (Schema::hasTable('core_command_runs') && !Schema::hasColumn('core_command_runs', 'thread_id')) {
            Schema::table('core_command_runs', function (Blueprint $table) {
                $table->unsignedBigInteger('thread_id')->nullable()->after('team_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('core_chat_messages') && Schema::hasColumn('core_chat_messages', 'thread_id')) {
            Schema::table('core_chat_messages', function (Blueprint $table) {
                try {
                    $table->dropForeign(['thread_id']);
                } catch (\Throwable) {
                    // FK may not exist if created by another migration
                }
            });
            Schema::table('core_chat_messages', function (Blueprint $table) {
                $table->dropColumn('thread_id');
            });
        }
        if (Schema::hasTable('core_chat_events') && Schema::hasColumn('core_chat_events', 'thread_id')) {
            Schema::table('core_chat_events', function (Blueprint $table) {
                $table->dropColumn('thread_id');
            });
        }
        if (Schema::hasTable('core_command_runs') && Schema::hasColumn('core_command_runs', 'thread_id')) {
            Schema::table('core_command_runs', function (Blueprint $table) {
                $table->dropColumn('thread_id');
            });
        }
    }
};
