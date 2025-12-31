<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_runs', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 64)->index(); // Für Multi-Step-Flows
            $table->integer('step')->default(0); // Schritt-Nummer (0 = initial, 1+ = Folge-Schritte)
            
            // Tool-Informationen
            $table->string('tool_name', 255);
            $table->json('arguments');
            
            // Status
            $table->enum('status', ['pending', 'waiting_input', 'completed', 'failed'])->default('pending')->index();
            $table->boolean('waiting_for_input')->default(false);
            
            // User-Input-Informationen
            $table->json('input_options')->nullable(); // Optionen für User-Auswahl (z.B. Teams)
            $table->string('next_tool', 255)->nullable(); // Nächstes Tool nach User-Input
            $table->json('next_tool_args')->nullable(); // Argumente für nächstes Tool
            
            // Context
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            
            // Resume-Informationen
            $table->timestamp('resumed_at')->nullable(); // Wann wurde der Run fortgesetzt?
            
            $table->timestamps();
            
            // Indexes
            $table->index(['conversation_id', 'step']);
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_runs');
    }
};

