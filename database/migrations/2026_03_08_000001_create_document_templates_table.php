<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable()->comment('NULL = system default');
            $table->string('key', 100)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('blade_view')->nullable()->comment('Blade view path for system templates');
            $table->longText('content')->nullable()->comment('DB HTML template (team overrides)');
            $table->json('schema')->nullable()->comment('JSON Schema for template data validation');
            $table->json('default_data')->nullable();
            $table->json('meta')->nullable()->comment('Paper config, header/footer HTML, etc.');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'key']);
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
