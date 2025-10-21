<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('core_chat_threads')) {
            return;
        }

        Schema::create('core_chat_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('core_chat_id')->index();
            $table->string('title')->nullable();
            $table->string('status')->default('open');
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_chat_threads');
    }
};
