<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            
            // Request-Details
            $table->text('description'); // Beschreibung der benötigten Funktionalität
            $table->text('use_case')->nullable(); // Konkreter Use-Case
            $table->string('suggested_name')->nullable(); // Vorschlag für Tool-Namen
            $table->string('category')->nullable(); // query, action, utility
            $table->string('module')->nullable(); // z.B. planner, okrs, core
            
            // Status-Tracking
            $table->string('status')->default('pending'); // pending, in_progress, completed, rejected
            $table->text('developer_notes')->nullable(); // Notizen vom Entwickler
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Metadaten
            $table->json('similar_tools')->nullable(); // Ähnliche Tools, die gefunden wurden
            $table->json('metadata')->nullable(); // Zusätzliche Metadaten
            
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('user_id');
            $table->index('team_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_requests');
    }
};

