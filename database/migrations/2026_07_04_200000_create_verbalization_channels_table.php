<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verbalization_channels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Berichts-Referenz (heute Tabellenname weiterhin verbalization_feeds;
            // semantisch ist das der Report, dem der Kanal gehoert).
            $table->foreignId('verbalization_feed_id')
                ->constrained('verbalization_feeds')
                ->cascadeOnDelete();

            // Kanal-Typ (kein Enum um flexibel zu bleiben — neue Kanaele kommen
            // ohne Migration). Beispiele: rss, email, pdf, slack, webhook, voice.
            $table->string('type', 40);

            // Kanal-spezifische Konfiguration:
            // - rss:     { }  (URL leitet sich aus uuid ab)
            // - email:   { to: [...], subject_template: "...", format: "html|markdown" }
            // - pdf:     { destination: "media_library|s3", template: "..." }
            // - slack:   { webhook_url: "...", channel: "#..." }
            // - webhook: { url: "...", secret: "..." }
            $table->json('config')->nullable();

            // Kanal-Cadence (Phase 2). Fuer Phase 1 null = an Report-Cadence gekoppelt.
            $table->string('cadence', 40)->nullable();

            $table->boolean('is_active')->default(true)->index();

            // Zuletzt ausgeliefert (Push-Kanaele) — Pull-Kanaele (rss) lassen es null.
            $table->timestamp('last_delivered_at')->nullable();

            $table->timestamps();

            // Ein Report kann pro Typ N Kanaele haben (mehrere Email-Empfaenger-Gruppen,
            // mehrere Slack-Channels ...). Deshalb kein unique(feed_id, type).
            $table->index(['verbalization_feed_id', 'type']);
        });

        // Daten-Migration: fuer jeden bestehenden Feed einen RSS-Kanal-Row anlegen
        // mit uuid = feed.uuid. Damit bleiben alle bestehenden Reader-URLs stabil.
        $feeds = DB::table('verbalization_feeds')->select('id', 'uuid', 'created_at', 'updated_at')->get();
        $now = now();
        foreach ($feeds as $feed) {
            DB::table('verbalization_channels')->insert([
                'uuid' => $feed->uuid,
                'verbalization_feed_id' => $feed->id,
                'type' => 'rss',
                'config' => json_encode([]),
                'cadence' => null,
                'is_active' => true,
                'last_delivered_at' => null,
                'created_at' => $feed->created_at ?? $now,
                'updated_at' => $feed->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('verbalization_channels');
    }
};
