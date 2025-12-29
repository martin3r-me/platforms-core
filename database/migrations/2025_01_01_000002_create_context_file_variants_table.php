<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_file_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('context_file_id')->constrained()->onDelete('cascade');
            
            // Variant-Informationen
            $table->string('variant_type', 50); // 'thumbnail', 'medium', 'large', etc.
            $table->string('token', 64)->unique(); // Eindeutiger Token für Dateiname
            $table->string('disk', 50)->default('local');
            $table->string('path'); // Flacher Pfad (nur Dateiname)
            
            // Dimensionen
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('file_size');
            
            $table->timestamps();
            
            $table->index(['context_file_id', 'variant_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_file_variants');
    }
};

