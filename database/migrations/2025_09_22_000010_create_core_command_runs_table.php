<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_command_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('team_id')->nullable()->index();
            $table->string('command_key');
            $table->string('impact')->default('low');
            $table->boolean('force_execute')->default(false);
            $table->json('slots')->nullable();
            $table->string('result_status')->nullable(); // ok|error|need_confirm
            $table->string('navigate')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_command_runs');
    }
};


