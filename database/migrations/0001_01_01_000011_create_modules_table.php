<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // z.B. "crm", "orders"
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('url')->nullable();
            $table->json('config')->nullable(); // für spätere flexible Daten
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
