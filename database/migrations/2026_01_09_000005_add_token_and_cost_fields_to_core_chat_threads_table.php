<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('core_chat_threads')) {
            return;
        }

        Schema::table('core_chat_threads', function (Blueprint $table) {
            if (!Schema::hasColumn('core_chat_threads', 'total_tokens_in')) {
                $table->unsignedBigInteger('total_tokens_in')->default(0)->after('meta');
            }
            if (!Schema::hasColumn('core_chat_threads', 'total_tokens_out')) {
                $table->unsignedBigInteger('total_tokens_out')->default(0)->after('total_tokens_in');
            }
            if (!Schema::hasColumn('core_chat_threads', 'total_tokens_cached')) {
                $table->unsignedBigInteger('total_tokens_cached')->default(0)->after('total_tokens_out');
            }
            if (!Schema::hasColumn('core_chat_threads', 'total_tokens_reasoning')) {
                $table->unsignedBigInteger('total_tokens_reasoning')->default(0)->after('total_tokens_cached');
            }
            if (!Schema::hasColumn('core_chat_threads', 'total_cost')) {
                $table->decimal('total_cost', 10, 4)->default(0)->after('total_tokens_reasoning');
            }
            if (!Schema::hasColumn('core_chat_threads', 'pricing_currency')) {
                $table->string('pricing_currency', 3)->default('USD')->after('total_cost');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('core_chat_threads')) {
            return;
        }

        Schema::table('core_chat_threads', function (Blueprint $table) {
            $columns = ['total_tokens_in', 'total_tokens_out', 'total_tokens_cached', 'total_tokens_reasoning', 'total_cost', 'pricing_currency'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('core_chat_threads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

