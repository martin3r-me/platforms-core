<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->text('daily_goal')->nullable();
            $table->integer('mood')->nullable(); // 1-5
            $table->integer('happiness')->nullable(); // 1-5
            $table->boolean('needs_support')->default(false);
            $table->boolean('hydrated')->default(false);
            $table->boolean('exercised')->default(false);
            $table->boolean('slept_well')->default(false);
            $table->boolean('focused_work')->default(false);
            $table->boolean('social_time')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
