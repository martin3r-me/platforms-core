<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('document_template_id')->nullable();
            $table->string('template_key', 100)->index()->comment('Denormalized for fast queries');
            $table->string('title');
            $table->json('data')->nullable()->comment('JSON snapshot of template data');
            $table->string('status', 20)->default('draft')->comment('draft, rendered, failed');
            $table->unsignedBigInteger('output_context_file_id')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('document_template_id')->references('id')->on('document_templates')->nullOnDelete();
            $table->foreign('output_context_file_id')->references('id')->on('context_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
