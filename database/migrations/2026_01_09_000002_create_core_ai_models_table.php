<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_ai_models', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('provider_id')->constrained('core_ai_providers')->cascadeOnDelete();

            // Provider-specific model identifier (e.g. "gpt-5.2")
            $table->string('model_id', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('category', 128)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_deprecated')->default(false);
            $table->timestamp('deprecated_at')->nullable();

            // Key technical capabilities (nullable when unknown)
            $table->unsignedInteger('context_window')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->date('knowledge_cutoff_date')->nullable();

            $table->boolean('supports_reasoning_tokens')->default(false);
            $table->boolean('supports_streaming')->nullable();
            $table->boolean('supports_function_calling')->nullable();
            $table->boolean('supports_structured_outputs')->nullable();

            // Pricing (per 1M tokens)
            $table->string('pricing_currency', 3)->default('USD');
            $table->decimal('price_input_per_1m', 10, 4)->nullable();
            $table->decimal('price_cached_input_per_1m', 10, 4)->nullable();
            $table->decimal('price_output_per_1m', 10, 4)->nullable();

            // Optional structured details
            $table->json('modalities')->nullable();
            $table->json('endpoints')->nullable();
            $table->json('features')->nullable();
            $table->json('tools')->nullable();

            // Raw provider response for traceability
            $table->json('api_metadata')->nullable();
            $table->timestamp('last_api_check')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['provider_id', 'model_id']);
            $table->index(['provider_id', 'is_active', 'is_deprecated']);
            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_ai_models');
    }
};


