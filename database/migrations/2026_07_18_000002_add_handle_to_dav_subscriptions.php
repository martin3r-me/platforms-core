<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dav_subscriptions', function (Blueprint $table) {
            // Öffentlicher Identifier im URL-Pfad (/dav/{handle}/…). Macht jedes Abo
            // zu einer eigenen URL -> eigener iOS-Account. Das Secret bleibt im Passwort.
            $table->string('handle', 32)->nullable()->unique()->after('team_id');
        });

        // Bestehende Abos mit einem Handle nachziehen.
        DB::table('dav_subscriptions')->whereNull('handle')->orderBy('id')->each(function ($row) {
            do {
                $handle = Str::random(16);
            } while (DB::table('dav_subscriptions')->where('handle', $handle)->exists());

            DB::table('dav_subscriptions')->where('id', $row->id)->update(['handle' => $handle]);
        });
    }

    public function down(): void
    {
        Schema::table('dav_subscriptions', function (Blueprint $table) {
            $table->dropColumn('handle');
        });
    }
};
