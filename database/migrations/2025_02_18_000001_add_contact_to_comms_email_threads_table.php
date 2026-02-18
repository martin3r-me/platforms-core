<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comms_email_threads', function (Blueprint $table) {
            // Polymorphic contact relation (CrmContact, Applicant, etc.)
            $table->string('contact_type')->nullable()->after('context_model_id');
            $table->unsignedBigInteger('contact_id')->nullable()->after('contact_type');

            $table->index(['contact_type', 'contact_id'], 'cet_contact_idx');
        });
    }

    public function down(): void
    {
        Schema::table('comms_email_threads', function (Blueprint $table) {
            $table->dropIndex('cet_contact_idx');
            $table->dropColumn(['contact_type', 'contact_id']);
        });
    }
};
