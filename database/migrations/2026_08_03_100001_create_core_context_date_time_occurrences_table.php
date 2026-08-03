<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shadow-Table: expandierte Einzeltermine einer (ggf. wiederkehrenden)
     * core_context_date_time-Zeile. Ein nicht-wiederkehrender Zeitpunkt hat genau
     * eine Occurrence; eine RRULE wird in N Occurrences aufgelöst. `is_exception`
     * markiert von der Regel abweichende Einzeltermine (EXDATE/RDATE-Override).
     */
    public function up(): void
    {
        Schema::create('core_context_date_time_occurrences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('core_context_date_time_id')
                ->constrained('core_context_date_times')
                ->cascadeOnDelete();

            // In UTC gespeichert, analog zur Parent-Zeile.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();

            $table->boolean('is_exception')->default(false);

            $table->timestamps();

            // Kalender-Range-Queries laufen über den expandierten Zeitraum.
            $table->index(['starts_at', 'ends_at'], 'core_ctx_dt_occ_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_context_date_time_occurrences');
    }
};
