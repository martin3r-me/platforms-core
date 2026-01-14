<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comms_channels', function (Blueprint $table) {
            $table->id();

            // Store at root/parent team level (Team::getRootTeam()) – same as provider connections.
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Link to a provider-connection (Postmark/Sipgate/WhatsApp...)
            $table->foreignId('comms_provider_connection_id')
                ->nullable()
                ->constrained('comms_provider_connections')
                ->nullOnDelete();

            // e.g. email, phone, whatsapp
            $table->string('type', 32);

            // e.g. postmark, sipgate, whatsapp_meta
            $table->string('provider', 64);

            // Human label in UI (optional)
            $table->string('name')->nullable();

            // Sender identifier / endpoint
            // - email: sales@company.de
            // - phone: +49172...
            // - whatsapp: +49172... or provider specific id (kept in meta)
            $table->string('sender_identifier', 255);

            // private (creator only) vs team (shared)
            $table->string('visibility', 16)->default('private');

            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'type', 'is_active']);
            $table->unique(['team_id', 'type', 'sender_identifier'], 'comms_channels_team_type_sender_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_channels');
    }
};

