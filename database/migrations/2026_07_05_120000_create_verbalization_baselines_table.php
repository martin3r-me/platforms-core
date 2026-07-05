<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verbalization_baselines', function (Blueprint $table) {
            $table->id();

            // Baseline ist Subject-lokal — verschiedene Feeds mit unterschiedlichen
            // Fenstern (7d/30d/...) teilen dieselbe Serie. Delta wird ad-hoc pro
            // Konsum berechnet.
            $table->string('subject_type', 100);
            $table->string('subject_id', 100);

            // Snapshot der Zahlen. Struktur: { metric_key: numeric_value, ... }
            // Kein Schema hart — Metric-Keys wandern mit Provider-Ausbau.
            $table->json('metrics');

            // Wann aufgenommen. Delta-Rechnung sucht Snapshot naeheste zu now()-window.
            $table->timestamp('captured_at')->useCurrent();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'captured_at'], 'vb_subject_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbalization_baselines');
    }
};
