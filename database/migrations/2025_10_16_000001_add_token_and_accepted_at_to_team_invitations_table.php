<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // token hinzufügen (unique)
        if (! Schema::hasColumn('team_invitations', 'token')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                $table->string('token')->nullable()->after('email');
            });

            Schema::table('team_invitations', function (Blueprint $table) {
                $table->unique('token');
            });
        }

        // accepted_at hinzufügen
        if (! Schema::hasColumn('team_invitations', 'accepted_at')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                $table->timestamp('accepted_at')->nullable()->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('team_invitations', 'accepted_at')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                $table->dropColumn('accepted_at');
            });
        }

        if (Schema::hasColumn('team_invitations', 'token')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                $table->dropUnique(['token']);
                $table->dropColumn('token');
            });
        }
    }
};


