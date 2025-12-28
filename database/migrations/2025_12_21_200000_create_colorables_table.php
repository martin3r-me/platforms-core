<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colorables', function (Blueprint $table) {
            $table->id();
            $table->string('color', 7); // Hex-Farbe (z.B. #FF5733)
            $table->string('colorable_type'); // Polymorphe Beziehung
            $table->unsignedBigInteger('colorable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null = Team-Farbe, gesetzt = persönlich
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete(); // Für Performance/Filter
            $table->timestamps();

            // Indizes für Performance
            $table->index(['colorable_type', 'colorable_id'], 'colorables_colorable_index');
            $table->index(['user_id', 'team_id'], 'colorables_user_team_index');
            // Unique Constraint: Eine Farbe kann nur einmal pro Entity zugeordnet werden (Team oder persönlich)
            $table->unique(['colorable_type', 'colorable_id', 'user_id'], 'colorables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colorables');
    }
};

