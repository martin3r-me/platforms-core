<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verbalization_recipes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Identifikator (z.B. "customer_brief", "weekly_status").
            $table->string('key', 100);

            // Display + Beschreibung
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Welchen Subject-Type bedient diese Recipe? (z.B. "planner_project")
            $table->string('subject_type', 100)->index();

            // Was wird gesammelt? Sammler interpretiert die Keys, die er kennt.
            // Beispiel: { "description": true, "frogs": { "enabled": true, "top_n": 3 }, ... }
            $table->json('sources');

            // Style-Bundle: { "address": "sie", "tone": "formal", "rhythm": "flowing",
            //                 "extra_instruction": "..." }
            $table->json('style');

            // Optional: Guard-Overrides (sonst Defaults).
            $table->json('guards')->nullable();

            // Optional: erwartete Frische-Strategie ("live" | "snapshot" | "snapshot_with_live_topup").
            $table->string('freshness_requirement', 40)->nullable();

            // Team-Scope: null = global verfuegbar; sonst nur fuer dieses Team.
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // key ist eindeutig je Team-Scope (global = team_id null).
            // FK-Name kuerzen, max 64 Zeichen.
            $table->unique(['key', 'team_id'], 'vrcp_key_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbalization_recipes');
    }
};
