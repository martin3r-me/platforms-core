<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_provider_connections', function (Blueprint $table) {
            $table->id();

            // Store at root/parent team level (Team::getRootTeam()).
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // e.g. postmark, whatsapp_meta, phone_system, ...
            $table->string('provider', 64);

            // Human label (optional) – helpful if we later support multiple connections per provider.
            $table->string('name')->nullable();

            $table->boolean('is_active')->default(true);

            // Secrets/config (server tokens, signing secrets, webhook creds, etc).
            // Use encrypted casts in the model.
            $table->longText('credentials')->nullable();

            // Non-secret metadata (ids, flags, misc)
            $table->json('meta')->nullable();

            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'provider', 'is_active']);
            $table->unique(['team_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_provider_connections');
    }
};

