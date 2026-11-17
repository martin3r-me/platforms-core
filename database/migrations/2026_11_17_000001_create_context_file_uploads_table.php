<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_file_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('file_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('total_size');
            $table->string('sha256', 64)->nullable();
            $table->string('disk', 50);
            $table->string('path');
            $table->string('status', 20)->default('pending'); // pending | uploaded | completed
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        // Dateien dürfen vorübergehend ohne Kontext existieren (upload.complete ohne Kontext → files.attach)
        Schema::table('context_files', function (Blueprint $table) {
            $table->string('context_type')->nullable()->change();
            $table->unsignedBigInteger('context_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_file_uploads');
    }
};
