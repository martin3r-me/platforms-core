<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Slug/Identifier
            $table->string('label'); // Anzeigbarer Name
            $table->string('color', 7)->nullable(); // Hex-Farbe (z.B. #FF5733)
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index für Team-Scope
            $table->index('team_id');
            // Unique Constraint: name muss innerhalb eines Teams eindeutig sein (oder global wenn team_id null)
            // MySQL behandelt NULL in unique constraints speziell - mehrere NULL-Werte sind erlaubt
            // Daher verwenden wir einen generierten Spalten-Index für bessere Kontrolle
            $table->unique(['name', 'team_id'], 'tags_name_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};

