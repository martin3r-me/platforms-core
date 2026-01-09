<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_ai_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // e.g. "openai", "anthropic"
            $table->string('key', 64)->unique();
            $table->string('name', 255);

            $table->boolean('is_active')->default(true);

            // Provider API base url (optional; can be overridden by config later)
            $table->string('base_url', 512)->nullable();

            // Any provider-level metadata (capabilities, notes, etc.)
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_ai_providers');
    }
};


