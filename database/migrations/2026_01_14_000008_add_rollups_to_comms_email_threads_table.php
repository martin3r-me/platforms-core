<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comms_email_threads', function (Blueprint $table) {
            // Defensive: avoid failing in partially-migrated environments
            if (!Schema::hasColumn('comms_email_threads', 'last_inbound_from')) {
                $table->text('last_inbound_from')->nullable()->after('subject'); // raw "Name <email>"
            }
            if (!Schema::hasColumn('comms_email_threads', 'last_inbound_from_address')) {
                $table->string('last_inbound_from_address', 255)->nullable()->after('last_inbound_from');
            }
            if (!Schema::hasColumn('comms_email_threads', 'last_inbound_at')) {
                $table->timestamp('last_inbound_at')->nullable()->after('last_inbound_from_address');
            }

            if (!Schema::hasColumn('comms_email_threads', 'last_outbound_to')) {
                $table->text('last_outbound_to')->nullable()->after('last_inbound_at'); // raw list / string
            }
            if (!Schema::hasColumn('comms_email_threads', 'last_outbound_to_address')) {
                $table->string('last_outbound_to_address', 255)->nullable()->after('last_outbound_to');
            }
            if (!Schema::hasColumn('comms_email_threads', 'last_outbound_at')) {
                $table->timestamp('last_outbound_at')->nullable()->after('last_outbound_to_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comms_email_threads', function (Blueprint $table) {
            if (Schema::hasColumn('comms_email_threads', 'last_outbound_at')) {
                $table->dropColumn('last_outbound_at');
            }
            if (Schema::hasColumn('comms_email_threads', 'last_outbound_to_address')) {
                $table->dropColumn('last_outbound_to_address');
            }
            if (Schema::hasColumn('comms_email_threads', 'last_outbound_to')) {
                $table->dropColumn('last_outbound_to');
            }
            if (Schema::hasColumn('comms_email_threads', 'last_inbound_at')) {
                $table->dropColumn('last_inbound_at');
            }
            if (Schema::hasColumn('comms_email_threads', 'last_inbound_from_address')) {
                $table->dropColumn('last_inbound_from_address');
            }
            if (Schema::hasColumn('comms_email_threads', 'last_inbound_from')) {
                $table->dropColumn('last_inbound_from');
            }
        });
    }
};

