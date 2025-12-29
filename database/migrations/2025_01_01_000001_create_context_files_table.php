<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_files', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique(); // Eindeutiger Token für Dateiname
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Kontext-Bezug (polymorph)
            $table->string('context_type');
            $table->unsignedBigInteger('context_id');
            $table->index(['context_type', 'context_id']);
            
            // Datei-Informationen
            $table->string('disk', 50)->default('local');
            $table->string('path'); // Flacher Pfad (nur Dateiname)
            $table->string('file_name'); // Token + Extension
            $table->string('original_name'); // Original-Dateiname für Download
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            
            // Bild-Dimensionen (optional)
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            
            // Optionen
            $table->boolean('keep_original')->default(false); // Original behalten bei Varianten
            
            // Metadaten
            $table->json('meta')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_files');
    }
};

