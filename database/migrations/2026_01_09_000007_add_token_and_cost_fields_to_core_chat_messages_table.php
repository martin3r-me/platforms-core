<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('core_chat_messages')) {
            return;
        }

        Schema::table('core_chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('core_chat_messages', 'tokens_cached')) {
                $table->unsignedBigInteger('tokens_cached')->default(0)->after('tokens_out');
            }
            if (!Schema::hasColumn('core_chat_messages', 'tokens_reasoning')) {
                $table->unsignedBigInteger('tokens_reasoning')->default(0)->after('tokens_cached');
            }
            if (!Schema::hasColumn('core_chat_messages', 'cost')) {
                $table->decimal('cost', 10, 4)->default(0)->after('tokens_reasoning');
            }
            if (!Schema::hasColumn('core_chat_messages', 'pricing_currency')) {
                $table->string('pricing_currency', 3)->default('USD')->after('cost');
            }
            if (!Schema::hasColumn('core_chat_messages', 'model_id')) {
                $table->string('model_id', 128)->nullable()->after('pricing_currency');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('core_chat_messages')) {
            return;
        }

        Schema::table('core_chat_messages', function (Blueprint $table) {
            $columns = ['tokens_cached', 'tokens_reasoning', 'cost', 'pricing_currency', 'model_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('core_chat_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

