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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained();
            $table->string('label');
            $table->string('billable_type');
            $table->string('billable_model');
            $table->unsignedInteger('count');
            $table->decimal('unit_price', 8, 4);
            $table->decimal('total', 10, 2);
            $table->json('details')->nullable(); // Tagesdaten, Preishistorie, alles was du willst
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
