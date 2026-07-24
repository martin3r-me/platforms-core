<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kernel-Tabellen der graph-basierten Autorisierung (ReBAC).
 *
 * Achsen:
 *   - authz_grant          → wer darf was, wo (scope_type = module | team | entity)
 *   - authz_scope_closure  → Erreichbarkeit im Org-Baum (materialisiert)
 *   - authz_resource_link  → welches Objekt hängt an welcher Entity
 *   - authz_capability     → Vokabular (Extensibilitäts-Anker)
 *   - authz_shadow_log     → protokollierte Abweichungen Graph vs. Legacy
 *
 * 'team' ist der Bootstrap-Scope (Team = Wurzel), solange organization den
 * Baum noch nicht materialisiert hat; kollabiert später in 'entity'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authz_capability', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // use | read | write | owner | (später module-verben)
            $table->string('applies_to');              // module | entity
            $table->unsignedInteger('rank')->default(0); // read 10 < write 20 < owner 30
            $table->string('label')->nullable();
        });

        DB::table('authz_capability')->insert([
            ['code' => 'use',   'applies_to' => 'module', 'rank' => 0,  'label' => 'Modul nutzen'],
            ['code' => 'read',  'applies_to' => 'entity', 'rank' => 10, 'label' => 'Lesen'],
            ['code' => 'write', 'applies_to' => 'entity', 'rank' => 20, 'label' => 'Schreiben'],
            ['code' => 'owner', 'applies_to' => 'entity', 'rank' => 30, 'label' => 'Owner'],
        ]);

        Schema::create('authz_grant', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');            // user | entity
            $table->unsignedBigInteger('subject_id');
            $table->string('capability');              // FK-lose Referenz auf authz_capability.code
            $table->string('scope_type');              // module | team | entity
            $table->unsignedBigInteger('scope_id')->nullable(); // team/entity id
            $table->string('scope_key')->nullable();   // module-key oder '*'
            $table->string('source')->nullable();      // provenienz: seed:team_user | org:role_assignment:{id}
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->foreignId('team_id');              // mandant
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_key']);
            $table->index(['team_id', 'subject_type', 'subject_id']);
        });

        Schema::create('authz_scope_closure', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestor_id');    // OrganizationEntity
            $table->unsignedBigInteger('descendant_id');  // OrganizationEntity
            $table->unsignedInteger('depth')->default(0); // 0 = self
            $table->foreignId('team_id');

            $table->unique(['ancestor_id', 'descendant_id']);
            $table->index('descendant_id');
            $table->index('team_id');
        });

        Schema::create('authz_resource_link', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type');             // PlannerProject | Invoice | ...
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('scope_id');      // Entity, an der das Objekt hängt
            $table->foreignId('team_id');

            $table->unique(['resource_type', 'resource_id', 'scope_id'], 'authz_reslink_unique');
            $table->index('scope_id');
        });

        Schema::create('authz_shadow_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ability');                    // Gate-Ability (view/update/delete/...)
            $table->string('capability')->nullable();     // gemappte Content-Capability
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->boolean('legacy_result');             // was die bestehende Policy entschied
            $table->boolean('graph_result');              // was der Graph entschieden hätte
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('ability');
            $table->index('created_at');
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authz_shadow_log');
        Schema::dropIfExists('authz_resource_link');
        Schema::dropIfExists('authz_scope_closure');
        Schema::dropIfExists('authz_grant');
        Schema::dropIfExists('authz_capability');
    }
};
