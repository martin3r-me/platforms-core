<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('type')->default('user')->after('id'); // 'user' oder 'ai_user'
            $table->foreignId('core_ai_model_id')->nullable()->after('type')->constrained('core_ai_models')->nullOnDelete();
            $table->text('instruction')->nullable()->after('core_ai_model_id');
            $table->foreignId('team_id')->nullable()->after('instruction')->constrained('teams')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['core_ai_model_id']);
            $table->dropForeign(['team_id']);
            $table->dropColumn(['type', 'core_ai_model_id', 'instruction', 'team_id']);
        });
    }
};
