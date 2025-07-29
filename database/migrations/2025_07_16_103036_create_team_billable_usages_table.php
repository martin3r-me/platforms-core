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
        Schema::create('team_billable_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained();
            $table->string('billable_model');          // Z.B. '\Platform\Planner\Models\PlannerTask'
            $table->string('billable_type');           // Z.B. 'per_item'
            $table->string('label');                   // Snapshot für spätere Anzeige
            $table->date('usage_date');
            $table->unsignedInteger('count')->default(0);
            $table->decimal('cost_per_unit', 8, 4);
            $table->decimal('total_cost', 10, 2);
            $table->json('pricing_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'billable_model', 'usage_date']); // Kein doppelter Eintrag pro Tag/Team/Model
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_billable_usages');
    }
};
