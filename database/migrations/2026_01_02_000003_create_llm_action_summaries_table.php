<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_action_summaries', function (Blueprint $table) {
            $table->id();
            
            // Kontext
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('trace_id', 32)->nullable()->unique()->index();
            $table->string('chain_id', 32)->nullable()->index();
            
            // Zusammenfassung
            $table->text('user_message')->nullable(); // Was hat der User gefragt?
            $table->text('summary')->nullable(); // Zusammenfassung der Aktionen (was wurde gemacht)
            $table->json('actions')->nullable(); // Detaillierte Liste der Aktionen
            $table->json('created_models')->nullable(); // Welche Models wurden erstellt?
            $table->json('updated_models')->nullable(); // Welche Models wurden aktualisiert?
            $table->json('deleted_models')->nullable(); // Welche Models wurden gelöscht?
            
            // Statistiken
            $table->integer('tools_executed')->default(0); // Anzahl der ausgeführten Tools
            $table->integer('models_created')->default(0);
            $table->integer('models_updated')->default(0);
            $table->integer('models_deleted')->default(0);
            
            // Metadaten
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_action_summaries');
    }
};

