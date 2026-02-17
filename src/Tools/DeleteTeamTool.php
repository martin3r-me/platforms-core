<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Team;

/**
 * core.teams.DELETE
 *
 * Löscht ein Team unwiderruflich. HOCHSENSIBLE Operation!
 *
 * Policy-Absicherung:
 * - Nur der Owner des Parent-Teams (bei Kind-Teams) oder der Team-Owner (bei Root-Teams) darf löschen.
 * - Persönliche Teams (personal_team=true) können NICHT gelöscht werden.
 * - Root-Teams mit Kind-Teams können NICHT gelöscht werden (Kind-Teams zuerst löschen).
 * - Teams mit aktiver Zahlungsmethode (Mollie) erfordern explizite force-Bestätigung.
 *
 * Kaskadierende Löschung (DB-Constraints cascadeOnDelete):
 * - team_invitations (Einladungen)
 * - team_user_last_modules (Letzte Modul-Zuordnungen)
 * - team_core_ai_models (AI-Model-Konfigurationen)
 * - team_counter_definitions + team_counter_events (Zähler)
 * - comms_channels (Kommunikationskanäle)
 * - comms_email_threads (E-Mail-Threads)
 * - comms_whatsapp_threads (WhatsApp-Threads)
 * - comms_provider_connections (Provider-Verbindungen)
 * - core_extra_field_definitions (Extra-Felder)
 * - core_lookups (Lookup-Tabellen)
 * - tags (Tags)
 * - colorables (Farbzuordnungen)
 * - context_files (Kontextdateien)
 * - modulables (Modul-Zuordnungen)
 * - Kind-Teams (parent_team_id cascadeOnDelete) – werden rekursiv mitgelöscht!
 *
 * Manuell bereinigt (kein DB-Cascade):
 * - team_user Pivot-Einträge (Mitgliedschaften)
 *
 * Audit-Log:
 * - Jede Tool-Ausführung wird automatisch über ToolExecution protokolliert.
 * - Model-Änderungen (inkl. Löschung) werden über ModelVersion festgehalten.
 * - Zusätzlich wird ein expliziter Log-Eintrag mit allen Team-Details geschrieben.
 */
class DeleteTeamTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.teams.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /core/teams/{id} – Löscht ein Team unwiderruflich (confirm=true ERFORDERLICH). '
            . 'ACHTUNG: Kaskadierende Löschung! Alle zugehörigen Daten werden mitgelöscht: '
            . 'Einladungen, Kanäle, E-Mail-Threads, WhatsApp-Threads, Extra-Felder, Lookups, Tags, '
            . 'Kontextdateien, AI-Model-Configs, Zähler, Kind-Teams (rekursiv!) und Modul-Zuordnungen. '
            . 'Schutz: Persönliche Teams und Root-Teams mit Kind-Teams können nicht gelöscht werden. '
            . 'Berechtigung: Nur Parent-Team-Owner (bei Kind-Teams) oder Team-Owner (bei Root-Teams).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des zu löschenden Teams. Nutze "core.teams.GET" um Teams zu finden.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true, um das Team wirklich zu löschen. Diese Aktion ist NICHT rückgängig zu machen!',
                ],
            ],
            'required' => ['team_id', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // ── Auth-Check ──────────────────────────────────────────
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // ── Confirmation-Check ──────────────────────────────────
            if (!($arguments['confirm'] ?? false)) {
                return ToolResult::error(
                    'CONFIRMATION_REQUIRED',
                    'Team-Löschung erfordert explizite Bestätigung. Setze confirm=true, um das Team unwiderruflich zu löschen. '
                    . 'WARNUNG: Alle zugehörigen Daten (Kanäle, Threads, Felder, Tags, Kind-Teams etc.) werden kaskadierend mitgelöscht!'
                );
            }

            // ── Validierung ─────────────────────────────────────────
            $teamId = (int) ($arguments['team_id'] ?? 0);
            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'team_id ist erforderlich und muss eine positive Zahl sein.');
            }

            $team = Team::find($teamId);
            if (!$team) {
                return ToolResult::error('NOT_FOUND', 'Team nicht gefunden.');
            }

            // ── Schutz: Persönliche Teams ───────────────────────────
            if ($team->personal_team) {
                return ToolResult::error(
                    'FORBIDDEN',
                    'Persönliche Teams können nicht gelöscht werden. Persönliche Teams sind an einen User gebunden und werden automatisch verwaltet.'
                );
            }

            // ── Schutz: Root-Teams mit Kind-Teams ───────────────────
            if ($team->isRootTeam() && $team->childTeams()->exists()) {
                $childCount = $team->childTeams()->count();
                return ToolResult::error(
                    'HAS_CHILDREN',
                    "Das Root-Team hat {$childCount} Kind-Team(s). Lösche zuerst alle Kind-Teams, bevor du das Root-Team löschen kannst. "
                    . 'Nutze core.teams.GET mit filter parent_team_id, um die Kind-Teams zu finden.'
                );
            }

            // ── Schutz: Teams mit aktiver Zahlungsmethode ───────────
            if (!empty($team->mollie_customer_id) || !empty($team->mollie_payment_method_id)) {
                return ToolResult::error(
                    'HAS_BILLING',
                    'Das Team hat eine aktive Zahlungsmethode (Mollie) konfiguriert. '
                    . 'Bitte entferne zuerst die Zahlungsmethode oder kontaktiere den Administrator, bevor das Team gelöscht werden kann.'
                );
            }

            // ── Berechtigungs-Check ─────────────────────────────────
            $accessError = $this->checkDeletePermission($team, $context);
            if ($accessError !== null) {
                return $accessError;
            }

            // ── Team-Daten für Response & Audit sichern ─────────────
            $teamData = [
                'id' => (int) $team->id,
                'name' => $team->name,
                'user_id' => (int) $team->user_id,
                'parent_team_id' => $team->parent_team_id ? (int) $team->parent_team_id : null,
                'personal_team' => (bool) $team->personal_team,
                'member_count' => $team->users()->count(),
                'created_at' => $team->created_at?->toIso8601String(),
            ];

            // ── Audit-Log: Expliziter Log-Eintrag vor Löschung ──────
            Log::info('[DeleteTeamTool] Team wird gelöscht', [
                'team' => $teamData,
                'deleted_by_user_id' => (int) $context->user->id,
                'deleted_by_user_name' => $context->user->name ?? 'unknown',
            ]);

            // ── Löschung in Transaktion ─────────────────────────────
            DB::transaction(function () use ($team) {
                // team_user Pivot hat keinen DB-Cascade → manuell bereinigen
                $team->users()->detach();

                // modulables Pivot manuell bereinigen (kein expliziter Cascade)
                $team->modules()->detach();

                // Team löschen (DB-Cascades erledigen den Rest)
                $team->delete();
            });

            return ToolResult::success([
                'deleted_team' => $teamData,
                'message' => "Team '{$teamData['name']}' (ID: {$teamData['id']}) wurde unwiderruflich gelöscht.",
                'cascade_info' => 'Alle zugehörigen Daten (Einladungen, Kanäle, Threads, Extra-Felder, Lookups, Tags, Kontextdateien, AI-Configs, Zähler, Modul-Zuordnungen) wurden kaskadierend mitgelöscht.',
            ]);
        } catch (\Throwable $e) {
            Log::error('[DeleteTeamTool] Fehler beim Löschen', [
                'team_id' => $arguments['team_id'] ?? null,
                'user_id' => $context->user?->id,
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Teams: ' . $e->getMessage());
        }
    }

    /**
     * Prüft die Berechtigung zum Löschen eines Teams.
     *
     * - Kind-Teams: Owner oder Admin des Parent-Teams darf löschen.
     * - Root-Teams: Nur der Team-Owner (teams.user_id) darf löschen.
     */
    private function checkDeletePermission(Team $team, ToolContext $context): ?ToolResult
    {
        $userId = (int) $context->user->id;

        // Kind-Team → Parent-Team-Owner oder -Admin darf löschen
        if ($team->parent_team_id !== null) {
            $parentTeam = $team->parentTeam;

            if (!$parentTeam) {
                return ToolResult::error('PARENT_TEAM_NOT_FOUND', 'Das Parent-Team konnte nicht gefunden werden.');
            }

            $parentMembership = $parentTeam->users()
                ->where('user_id', $userId)
                ->first();

            if (!$parentMembership) {
                return ToolResult::error(
                    'ACCESS_DENIED',
                    'Du bist kein Mitglied des Parent-Teams und darfst dieses Kind-Team nicht löschen.'
                );
            }

            $parentRole = $parentMembership->pivot->role ?? null;
            if (!in_array($parentRole, [TeamRole::OWNER->value, TeamRole::ADMIN->value], true)) {
                return ToolResult::error(
                    'ACCESS_DENIED',
                    'Nur Owner oder Admin des Parent-Teams dürfen Kind-Teams löschen.'
                );
            }

            return null; // Berechtigt
        }

        // Root-Team → nur Team-Owner (teams.user_id) darf löschen
        if ((int) $team->user_id !== $userId) {
            return ToolResult::error(
                'ACCESS_DENIED',
                'Nur der Team-Owner darf ein Root-Team löschen.'
            );
        }

        return null; // Berechtigt
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['core', 'team', 'delete', 'destructive'],
            'risk_level' => 'destructive',
            'requires_auth' => true,
            'requires_team' => false,
            'idempotent' => false,
            'confirmation_required' => true,
            'related_tools' => ['core.teams.GET', 'core.teams.POST', 'core.context.GET'],
        ];
    }
}
