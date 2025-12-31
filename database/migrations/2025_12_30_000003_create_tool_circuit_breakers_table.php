<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_circuit_breakers', function (Blueprint $table) {
            $table->id();
            $table->string('service_name', 255)->unique(); // z.B. 'openai', 'anthropic'
            $table->string('state', 20)->default('closed'); // closed, open, half_open
            $table->integer('failure_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('opened_at')->nullable(); // Wann wurde Circuit geöffnet
            $table->timestamps();
            
            // Indexes
            $table->index('state');
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_circuit_breakers');
    }
};

