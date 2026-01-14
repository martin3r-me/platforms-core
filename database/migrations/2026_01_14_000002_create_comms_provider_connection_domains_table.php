<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_provider_connection_domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('comms_provider_connection_id')
                ->constrained('comms_provider_connections')
                ->cascadeOnDelete();

            // e.g. bhgdigital.de, company.de
            $table->string('domain', 255);

            // e.g. sending, inbound, tracking (kept generic for other providers too)
            $table->string('purpose', 64)->default('sending');

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['comms_provider_connection_id', 'domain', 'purpose'], 'cpcd_connection_domain_purpose_unique');
            $table->index(['domain', 'purpose'], 'cpcd_domain_purpose_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_provider_connection_domains');
    }
};

