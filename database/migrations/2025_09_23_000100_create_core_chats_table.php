<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('team_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('total_tokens_in')->default(0);
            $table->unsignedBigInteger('total_tokens_out')->default(0);
            $table->string('status')->default('active'); // active|archived
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_chats');
    }
};


