<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modulables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->morphs('modulable'); // modulable_id, modulable_type (z. B. User, Team, etc.)
            $table->string('role')->nullable(); // z. B. "admin", "editor"
            $table->boolean('enabled')->default(true);
            $table->string('guard')->default('web'); // Optional: für Multi-Guard-Fälle
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modulables');
    }
};
