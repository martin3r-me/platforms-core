<?php

namespace Platform\Core\Health\Support;

use Illuminate\Database\Schema\Blueprint;

/**
 * Schema-Helper fuer Snapshot-Tabellen. Stellt das Standard-Spalten-Set
 * zur Verfuegung — Module ergaenzen ihren snapshotable_id-FK und ggf.
 * eigene modul-spezifische Spalten.
 *
 * Beispiel-Migration:
 *
 *   Schema::create('helpdesk_board_snapshots', function (Blueprint $table) {
 *       $table->id();
 *       $table->uuid('uuid')->unique();
 *       $table->foreignId('helpdesk_board_id')
 *           ->constrained('helpdesk_boards', 'id', 'hbs_board_fk')
 *           ->cascadeOnDelete();
 *
 *       HealthSnapshotSchema::columns($table);
 *
 *       // ... modul-spezifische Spalten hier
 *
 *       $table->timestamps();
 *       $table->unique(['helpdesk_board_id', 'taken_on'], 'hbs_board_day_uniq');
 *       $table->index(['helpdesk_board_id', 'taken_at'], 'hbs_board_taken_idx');
 *   });
 *
 * Die genauen Prefix-Namen muss das Modul selbst vergeben — wir bleiben
 * unter 64 Zeichen.
 */
class HealthSnapshotSchema
{
    /**
     * Fuegt das Standard-Snapshot-Spalten-Set zum Blueprint hinzu.
     *
     * Spalten:
     *  - taken_at (datetime), taken_on (date), trigger (string)
     *  - team_id (FK auf teams, nullOnDelete)
     *  - health_score (tinyint nullable), health_color (string nullable)
     *  - worst_axis (string nullable), axis_scores (json nullable)
     *  - confidence_score (tinyint), confidence_reason (string nullable)
     *  - prev_snapshot_id (unsignedBigInt nullable, self-FK — Caller setzt FK separat)
     *  - delta_health_score (int nullable), last_movement_at (datetime nullable)
     *  - frozen_context (json nullable)
     */
    public static function columns(Blueprint $table): void
    {
        // Snapshot-Meta
        $table->dateTime('taken_at');
        $table->date('taken_on');
        $table->string('trigger', 32)->default('cron'); // cron|manual|backfill|event:*

        // Team-Scope
        $table->foreignId('team_id')->nullable()
            ->constrained('teams')
            ->nullOnDelete();

        // Composite-Health (worst-of-Color + gewichteter Score + Confidence-Gate)
        $table->unsignedTinyInteger('health_score')->nullable();
        $table->string('health_color', 16)->nullable();

        // Welche Achse ist schwach? Plus per-axis-Detail.
        $table->string('worst_axis', 32)->nullable();
        $table->json('axis_scores')->nullable();

        // Confidence — wie belastbar ist der Snapshot?
        $table->unsignedTinyInteger('confidence_score')->default(0);
        $table->string('confidence_reason', 255)->nullable();

        // Frozen Context (denormalisierte Container-Felder zum Zeitpunkt)
        $table->json('frozen_context')->nullable();

        // Bewegung — Delta zum vorigen Snapshot
        $table->unsignedBigInteger('prev_snapshot_id')->nullable();
        $table->integer('delta_health_score')->nullable();
        $table->dateTime('last_movement_at')->nullable();
    }

    /**
     * Standard-Spalten-Set fuer einen Snapshot-Subdetail-Eintrag
     * (z.B. ein "top frog" oder "top ticket" das in einer Sub-Tabelle steht).
     *
     * Liefert nur die snapshot_id-Verknuepfung und ein rank-Feld. Sub-Tabellen
     * unterscheiden sich semantisch zu sehr fuer mehr Standardisierung.
     */
    public static function subdetailColumns(Blueprint $table, string $snapshotTable, string $fkName): void
    {
        $table->foreignId('snapshot_id')
            ->constrained($snapshotTable, 'id', $fkName)
            ->cascadeOnDelete();
        $table->unsignedTinyInteger('rank')->nullable();
    }
}
