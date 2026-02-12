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
        // Add LLM verification columns to definitions table
        Schema::table('core_extra_field_definitions', function (Blueprint $table) {
            $table->boolean('verify_by_llm')->default(false)->after('options');
            $table->text('verify_instructions')->nullable()->after('verify_by_llm');
        });

        // Add verification status columns to values table
        Schema::table('core_extra_field_values', function (Blueprint $table) {
            $table->string('verification_status', 32)->nullable()->after('value'); // pending|verifying|verified|rejected|error
            $table->json('verification_result')->nullable()->after('verification_status'); // LLM response, confidence, details
            $table->timestamp('verified_at')->nullable()->after('verification_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core_extra_field_definitions', function (Blueprint $table) {
            $table->dropColumn(['verify_by_llm', 'verify_instructions']);
        });

        Schema::table('core_extra_field_values', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'verification_result', 'verified_at']);
        });
    }
};
