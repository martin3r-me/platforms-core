<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('NULL = root level');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('share_token', 64)->nullable()->unique();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('document_folders')->nullOnDelete();
            $table->index(['team_id', 'parent_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('document_folder_id')->nullable()->after('team_id');
            $table->foreign('document_folder_id')->references('id')->on('document_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['document_folder_id']);
            $table->dropColumn('document_folder_id');
        });

        Schema::dropIfExists('document_folders');
    }
};
