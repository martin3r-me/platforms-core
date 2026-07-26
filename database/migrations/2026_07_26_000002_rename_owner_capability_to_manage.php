<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capability-Stufe umbenannt: 'owner' → 'manage'. "owner" war irreführend
 * (kollidierte mit Ersteller-Ownership); die Stufe ist ein Zugriffs-Level
 * (sehen+bearbeiten+verwalten), kein Besitz.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('authz_capability')) {
            DB::table('authz_capability')->where('code', 'owner')
                ->update(['code' => 'manage', 'label' => 'Verwalten']);
        }
        if (Schema::hasTable('authz_grant')) {
            DB::table('authz_grant')->where('capability', 'owner')
                ->update(['capability' => 'manage']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('authz_capability')) {
            DB::table('authz_capability')->where('code', 'manage')
                ->update(['code' => 'owner', 'label' => 'Owner']);
        }
        if (Schema::hasTable('authz_grant')) {
            DB::table('authz_grant')->where('capability', 'manage')
                ->update(['capability' => 'owner']);
        }
    }
};
