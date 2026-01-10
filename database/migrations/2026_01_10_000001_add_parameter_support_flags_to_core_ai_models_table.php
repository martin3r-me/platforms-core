<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_ai_models', function (Blueprint $table) {
            // Model-specific parameter support (Responses API can reject some params).
            $table->boolean('supports_temperature')->nullable()->after('supports_structured_outputs');
            $table->boolean('supports_top_p')->nullable()->after('supports_temperature');
            $table->boolean('supports_presence_penalty')->nullable()->after('supports_top_p');
            $table->boolean('supports_frequency_penalty')->nullable()->after('supports_presence_penalty');
        });
    }

    public function down(): void
    {
        Schema::table('core_ai_models', function (Blueprint $table) {
            $table->dropColumn([
                'supports_temperature',
                'supports_top_p',
                'supports_presence_penalty',
                'supports_frequency_penalty',
            ]);
        });
    }
};


