<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('undo_operations', function (Blueprint $table) {
            $table->id();
            
            // Operation-Details
            $table->string('operation_type')->index(); // 'undo', 'redo'
            $table->string('status')->default('pending')->index(); // 'pending', 'completed', 'failed'
            
            // Referenz zur Model-Version
            $table->foreignId('model_version_id')->constrained('model_versions')->onDelete('cascade');
            
            // Kontext
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('trace_id', 32)->nullable()->index();
            
            // Ergebnis
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->json('result_data')->nullable(); // Ergebnis der Undo-Operation
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('undo_operations');
    }
};

