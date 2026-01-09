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
            if (!Schema::hasColumn('core_chat_threads', 'model_id')) {
                $table->string('model_id', 128)->nullable()->after('pricing_currency');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('core_chat_threads')) {
            return;
        }

        Schema::table('core_chat_threads', function (Blueprint $table) {
            if (Schema::hasColumn('core_chat_threads', 'model_id')) {
                $table->dropColumn('model_id');
            }
        });
    }
};

