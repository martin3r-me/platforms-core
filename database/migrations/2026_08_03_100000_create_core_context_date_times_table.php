<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_context_date_times', function (Blueprint $table) {
            $table->id();

            // Polymorpher Kontext (an welche Entity hängt der Zeitpunkt?)
            $table->string('context_type');
            $table->unsignedBigInteger('context_id');

            // Art des Zeitpunkts – Werte aus Platform\Core\Enums\ContextDateTimeKind
            // (start, end, due, milestone, reminder, review, deadline, custom).
            $table->string('kind');
            $table->string('label')->nullable();

            // Zeitpunkte werden IMMER in UTC gespeichert; die IANA-Zone dient der
            // Rück-Umrechnung/Anzeige und als Basis für die RRULE-Expansion.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone')->default('Europe/Berlin');

            // Wiederholungsregel (iCalendar RRULE), z.B. "FREQ=WEEKLY;BYDAY=MO".
            $table->string('recurrence_rrule')->nullable();
            $table->boolean('is_all_day')->default(false);

            // Herkunft des Datensatzes (für Migrations-/Import-Tracking).
            $table->string('source')->nullable();

            // Kalender-Sync (CalDAV/iCalendar).
            $table->boolean('calendar_sync_enabled')->default(false);
            $table->string('icalendar_uid')->nullable();
            $table->dateTime('last_synced_at')->nullable();

            $table->unsignedBigInteger('team_id');

            $table->timestamps();
            $table->softDeletes();

            // Composite-Indizes gemäß Zugriffs-Pfaden:
            // 1) alle Zeitpunkte einer Entity, 2) Zeitraum-Queries gefiltert nach
            // Art, 3) Team-Kalender chronologisch.
            $table->index(['context_type', 'context_id'], 'core_ctx_dt_context_idx');
            $table->index(['starts_at', 'ends_at', 'kind'], 'core_ctx_dt_range_kind_idx');
            $table->index(['team_id', 'starts_at'], 'core_ctx_dt_team_starts_idx');
            $table->index('icalendar_uid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_context_date_times');
    }
};
