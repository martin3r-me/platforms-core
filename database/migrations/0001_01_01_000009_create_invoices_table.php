<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained();
            $table->string('number')->nullable(); // Rechnungsnummer (optional, für spätere Logik)
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_net', 10, 2);
            $table->decimal('tax_percent', 4, 2);
            $table->decimal('total_tax', 10, 2);
            $table->decimal('total_gross', 10, 2);
            $table->string('status')->default('open'); // z. B. open, paid, cancelled
            $table->json('meta')->nullable(); // Für Kundendaten, Notizen etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
