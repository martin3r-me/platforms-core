<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('exported_by_user_id')->nullable();
            $table->string('renderer', 50)->default('pdf')->comment('pdf, canva, ...');
            $table->unsignedBigInteger('output_context_file_id')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending, processing, complete, failed');
            $table->text('error_message')->nullable();
            $table->json('renderer_options')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('output_context_file_id')->references('id')->on('context_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_exports');
    }
};
