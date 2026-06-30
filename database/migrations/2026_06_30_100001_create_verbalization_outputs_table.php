<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verbalization_outputs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Wenn null: ad-hoc-Verbalisierung (nicht feed-getrieben). Wir protokollieren trotzdem.
            $table->foreignId('feed_id')->nullable()->constrained('verbalization_feeds')->cascadeOnDelete();

            $table->string('recipe_key', 100);
            $table->string('subject_type', 100);
            $table->string('subject_id', 100); // string statt int, falls UUID-Subjects spaeter
            $table->string('subject_label', 255)->nullable(); // Identity->primaryName, fuer Item-Titel

            $table->mediumText('prose'); // Markdown

            // Telemetrie
            $table->string('llm_provider', 40)->nullable();
            $table->string('llm_model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();

            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

            $table->timestamps();

            // Lookup-Indizes (kurze Namen wegen 64-Zeichen-Limit)
            $table->index(['feed_id', 'created_at'], 'voutp_feed_created_idx');
            $table->index(['subject_type', 'subject_id', 'created_at'], 'voutp_subj_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbalization_outputs');
    }
};
