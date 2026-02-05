<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comms_email_threads', function (Blueprint $table) {
            $table->string('context_model')->nullable()->after('subject');
            $table->unsignedBigInteger('context_model_id')->nullable()->after('context_model');

            $table->index(['context_model', 'context_model_id'], 'cet_context_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comms_email_threads', function (Blueprint $table) {
            $table->dropIndex('cet_context_idx');
            $table->dropColumn(['context_model', 'context_model_id']);
        });
    }
};
