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
        // Add AutoFill columns to definitions table
        Schema::table('core_extra_field_definitions', function (Blueprint $table) {
            $table->string('auto_fill_source', 32)->nullable()->after('verify_instructions'); // llm|websearch|null
            $table->text('auto_fill_prompt')->nullable()->after('auto_fill_source');
        });

        // Add auto_filled tracking to values table
        Schema::table('core_extra_field_values', function (Blueprint $table) {
            $table->boolean('auto_filled')->default(false)->after('verified_at');
            $table->timestamp('auto_filled_at')->nullable()->after('auto_filled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core_extra_field_definitions', function (Blueprint $table) {
            $table->dropColumn(['auto_fill_source', 'auto_fill_prompt']);
        });

        Schema::table('core_extra_field_values', function (Blueprint $table) {
            $table->dropColumn(['auto_filled', 'auto_filled_at']);
        });
    }
};
