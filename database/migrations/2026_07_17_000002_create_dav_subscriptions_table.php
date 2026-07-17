<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dav_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            // Zuständiges Modul + Protokoll (z. B. crm/carddav, planner/caldav).
            $table->string('module');
            $table->string('type');
            // Verweis auf die konkrete Ressource des Moduls (z. B. Kontaktlisten-ID).
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('secret', 64)->unique();
            $table->string('name', 255);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'team_id']);
            $table->index(['module', 'type']);
            $table->index('resource_id');
        });

        // Bestehende CRM-CardDAV-Abos übernehmen (behalten ihr Secret -> Geräte
        // müssen nicht neu eingerichtet werden). Danach die alte Tabelle entfernen.
        if (Schema::hasTable('crm_carddav_subscriptions')) {
            DB::table('crm_carddav_subscriptions')->orderBy('id')->each(function ($row) {
                DB::table('dav_subscriptions')->insert([
                    'user_id'      => $row->user_id,
                    'team_id'      => $row->team_id,
                    'module'       => 'crm',
                    'type'         => 'carddav',
                    'resource_id'  => $row->contact_list_id,
                    'secret'       => $row->secret,
                    'name'         => $row->name,
                    'last_used_at' => $row->last_used_at,
                    'expires_at'   => $row->expires_at,
                    'revoked_at'   => $row->revoked_at,
                    'created_at'   => $row->created_at,
                    'updated_at'   => $row->updated_at,
                ]);
            });

            Schema::drop('crm_carddav_subscriptions');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dav_subscriptions');
    }
};
