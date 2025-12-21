<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->string('taggable_type'); // Polymorphe Beziehung
            $table->unsignedBigInteger('taggable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null = Team-Tag, gesetzt = persönlich
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete(); // Für Performance/Filter
            $table->timestamps();

            // Indizes für Performance
            $table->index(['taggable_type', 'taggable_id'], 'taggables_taggable_index');
            $table->index(['tag_id', 'user_id'], 'taggables_tag_user_index');
            $table->index('team_id');
            // Unique Constraint: Ein Tag kann nur einmal pro Entity zugeordnet werden (Team oder persönlich)
            $table->unique(['tag_id', 'taggable_type', 'taggable_id', 'user_id'], 'taggables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};

