<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obsidian_vaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('driver', 32)->default('s3');
            $table->text('bucket');
            $table->string('region', 64)->nullable();
            $table->text('endpoint')->nullable();
            $table->text('access_key');
            $table->text('secret_key');
            $table->string('prefix')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'slug'], 'obsidian_vault_user_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obsidian_vaults');
    }
};
