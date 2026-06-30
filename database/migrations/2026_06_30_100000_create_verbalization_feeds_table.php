<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verbalization_feeds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('slug', 100)->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();

            // Was wird verbalisiert?
            // subject_type kann bei mixed-type-feeds null sein (recipes-map definiert dann pro Subject-Type)
            $table->string('subject_type', 100)->nullable()->index();

            // Wer wird ausgewaehlt?
            // { mode: "single", id: 53 }
            // { mode: "list",   ids: [53,27,119] }
            // { mode: "entity", entity_id: 137 }
            $table->json('subject_selector');

            // Recipe pro Subject-Type — Mixed-Type bereits vorgesehen.
            // { "planner_project": "customer_brief", "helpdesk_board": "customer_brief" }
            $table->json('recipes');

            // "history" = jede Verbalisierung ist ein eigenes Item (Variante A/C)
            // "snapshot" = nur juengste je Subject (Variante B)
            $table->string('item_strategy', 20)->default('history');

            // Optional: bevorzugter Provider/Modell
            $table->string('llm_provider', 40)->nullable();
            $table->string('llm_model', 100)->nullable();

            // Zugriff + Refresh
            $table->string('access', 20)->default('public');  // 'public' | 'team'
            $table->string('refresh_strategy', 30)->default('on_request');  // 'on_request' | 'cron_daily' | 'cron_weekly'
            $table->unsignedInteger('retention_items')->default(50);
            $table->boolean('is_active')->default(true)->index();

            // Wann zuletzt refreshed?
            $table->timestamp('last_refreshed_at')->nullable();

            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['slug', 'team_id'], 'vfeed_slug_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbalization_feeds');
    }
};
