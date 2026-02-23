<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_public_form_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->unsignedBigInteger('team_id');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id']);
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Migrate existing rec_applicants.public_token values
        if (Schema::hasTable('rec_applicants') && Schema::hasColumn('rec_applicants', 'public_token')) {
            $applicants = DB::table('rec_applicants')
                ->whereNotNull('public_token')
                ->where('public_token', '!=', '')
                ->get(['id', 'public_token', 'team_id', 'created_by_user_id', 'created_at']);

            foreach ($applicants as $applicant) {
                DB::table('core_public_form_links')->insert([
                    'token' => $applicant->public_token,
                    'linkable_type' => 'rec_applicant',
                    'linkable_id' => $applicant->id,
                    'team_id' => $applicant->team_id,
                    'is_active' => true,
                    'created_by_user_id' => $applicant->created_by_user_id,
                    'created_at' => $applicant->created_at,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_public_form_links');
    }
};
