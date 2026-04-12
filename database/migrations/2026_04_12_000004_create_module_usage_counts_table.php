<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_usage_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->unsignedInteger('visit_count')->default(0);
            $table->timestamp('last_visited_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'team_id', 'module_key']);
            $table->index(['user_id', 'team_id', 'visit_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_usage_counts');
    }
};
