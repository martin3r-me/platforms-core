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
        Schema::create('team_user_last_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->string('module_key')->nullable()->comment('z.B. planner, cms, okr, etc.');
            $table->timestamps();

            // Unique constraint: Ein User kann pro Team nur einen Eintrag haben
            $table->unique(['user_id', 'team_id']);
            
            // Index für schnelle Abfragen
            $table->index(['user_id', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_user_last_modules');
    }
};

