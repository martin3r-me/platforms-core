<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Module;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

/**
 * LLM-Tool zum Aktivieren/Deaktivieren von Modulen pro Team-User.
 *
 * Spiegelt die UI-Logik aus ModalModules::toggleMatrix() 1:1 wider.
 * Policy: Nur User mit OWNER-Rolle im aktuellen Team dürfen Module
 * für Team-Mitglieder aktivieren oder deaktivieren.
 */
class ToggleModuleTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.modules.PUT';
    }

    public function getDescription(): string
    {
        return 'Aktiviert oder deaktiviert ein Modul für einen User im aktuellen Team. '
            . 'Nur Team-Owner dürfen diese Aktion ausführen (identisch zur UI-Berechtigung). '
            . 'Bei Parent-Modulen (scope_type=parent) wird die Zuweisung im Root-Team vorgenommen und gilt nur dort. '
            . 'Nutze "core.modules.GET" um verfügbare Module zu sehen und "core.context.GET" mit include_members=true um Team-Mitglieder zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Users, für den das Modul aktiviert/deaktiviert werden soll. Der User muss Mitglied des aktuellen Teams sein.',
                ],
                'module_key' => [
                    'type' => 'string',
                    'description' => 'Key des Moduls (z.B. "planner", "crm", "okr"). Nutze "core.modules.GET" um verfügbare Module zu sehen.',
                ],
                'enabled' => [
                    'type' => 'boolean',
                    'description' => 'true = Modul aktivieren, false = Modul deaktivieren. Wenn nicht angegeben, wird der aktuelle Status umgeschaltet (Toggle).',
                ],
            ],
            'required' => ['user_id', 'module_key'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $user = $context->user;
            if (!$user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // --- Parameter validieren ---

            $targetUserId = $arguments['user_id'] ?? null;
            if ($targetUserId === null || $targetUserId === '' || $targetUserId === 0) {
                return ToolResult::error('VALIDATION_ERROR', 'user_id ist erforderlich.');
            }
            $targetUserId = (int) $targetUserId;
            if ($targetUserId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'user_id muss eine positive Zahl sein.');
            }

            $moduleKey = $arguments['module_key'] ?? null;
            if (!is_string($moduleKey) || $moduleKey === '') {
                return ToolResult::error('VALIDATION_ERROR', 'module_key ist erforderlich. Nutze "core.modules.GET" um verfügbare Module zu sehen.');
            }

            $explicitEnabled = array_key_exists('enabled', $arguments) ? $arguments['enabled'] : null;

            // --- Aktuelles Team bestimmen (MCP Session Override berücksichtigen) ---

            $currentTeam = $context->team;
            if (!$currentTeam) {
                // Fallback: currentTeamRelation vom User
                $currentTeam = $user->currentTeamRelation;
            }
            if (!$currentTeam) {
                return ToolResult::error('TEAM_ERROR', 'Kein aktuelles Team gefunden. Nutze "core.team.switch" um in ein Team zu wechseln.');
            }

            // --- Policy: Nur OWNER darf Module-Freigaben vergeben (identisch zur UI) ---

            $membership = $currentTeam->users()->where('user_id', $user->id)->first();
            if (!$membership) {
                return ToolResult::error('ACCESS_DENIED', 'Du bist kein Mitglied des aktuellen Teams.');
            }

            $userRole = $membership->pivot->role ?? null;
            if ($userRole !== TeamRole::OWNER->value) {
                return ToolResult::error(
                    'ACCESS_DENIED',
                    'Nur Team-Owner dürfen Module für Mitglieder aktivieren/deaktivieren. '
                    . 'Deine aktuelle Rolle in diesem Team: ' . ($userRole ?? 'keine') . '.'
                );
            }

            // --- Modul laden ---

            $module = Module::where('key', $moduleKey)->first();
            if (!$module) {
                return ToolResult::error('MODULE_NOT_FOUND', 'Das Modul "' . $moduleKey . '" wurde nicht gefunden. Nutze "core.modules.GET" um verfügbare Module zu sehen.');
            }

            // --- Scope: Root-Team vs. aktuelles Team ---

            if ($module->isRootScoped()) {
                $rootTeam = $currentTeam->getRootTeam();

                // Identisch zur UI: In Kind-Teams können Parent-Module nicht vergeben werden
                if ($currentTeam->id !== $rootTeam->id) {
                    return ToolResult::error(
                        'SCOPE_ERROR',
                        'Das Modul "' . $moduleKey . '" ist ein Parent-Modul (scope_type=parent) und kann nur im Root-Team vergeben werden. '
                        . 'Aktuelles Team: "' . $currentTeam->name . '" (ID: ' . $currentTeam->id . '). '
                        . 'Root-Team: "' . $rootTeam->name . '" (ID: ' . $rootTeam->id . '). '
                        . 'Nutze "core.team.switch" um ins Root-Team zu wechseln.'
                    );
                }

                $teamId = $rootTeam->id;
            } else {
                $teamId = $currentTeam->id;
            }

            // --- Ziel-User laden und prüfen ---

            $targetUser = User::find($targetUserId);
            if (!$targetUser) {
                return ToolResult::error('USER_NOT_FOUND', 'Der User mit ID ' . $targetUserId . ' wurde nicht gefunden.');
            }

            // Prüfen ob Ziel-User Mitglied des relevanten Teams ist
            $targetIsMember = $currentTeam->users()->where('user_id', $targetUser->id)->exists();
            if (!$targetIsMember) {
                return ToolResult::error(
                    'USER_NOT_IN_TEAM',
                    'Der User "' . ($targetUser->name ?? $targetUser->email) . '" (ID: ' . $targetUserId . ') ist kein Mitglied des Teams "' . $currentTeam->name . '". '
                    . 'Nutze "core.context.GET" mit include_members=true um alle Team-Mitglieder zu sehen.'
                );
            }

            // --- Toggle-Logik (identisch zu ModalModules::toggleMatrix) ---

            $alreadyAssigned = $targetUser->modules()
                ->where('module_id', $module->id)
                ->wherePivot('team_id', $teamId)
                ->exists();

            // Bestimme gewünschten Zustand
            if ($explicitEnabled !== null) {
                $shouldBeEnabled = (bool) $explicitEnabled;
            } else {
                // Toggle: Umkehren des aktuellen Zustands
                $shouldBeEnabled = !$alreadyAssigned;
            }

            $action = null;

            if ($shouldBeEnabled && !$alreadyAssigned) {
                // Aktivieren: Pivot erstellen
                $targetUser->modules()->attach($module->id, [
                    'role' => null,
                    'enabled' => true,
                    'guard' => 'web',
                    'team_id' => $teamId,
                ]);
                $action = 'activated';
            } elseif (!$shouldBeEnabled && $alreadyAssigned) {
                // Deaktivieren: Pivot löschen (identisch zur UI)
                $targetUser->modules()->newPivotStatement()
                    ->where('modulable_id', $targetUser->id)
                    ->where('modulable_type', User::class)
                    ->where('module_id', $module->id)
                    ->where('team_id', $teamId)
                    ->delete();
                $action = 'deactivated';
            } else {
                // Bereits im gewünschten Zustand
                $action = $shouldBeEnabled ? 'already_active' : 'already_inactive';
            }

            $teamLabel = $module->isRootScoped() ? 'Root-Team' : 'Team';

            return ToolResult::success([
                'action' => $action,
                'message' => match ($action) {
                    'activated' => 'Modul "' . $moduleKey . '" wurde für User "' . ($targetUser->name ?? $targetUser->email) . '" im ' . $teamLabel . ' "' . $currentTeam->name . '" aktiviert.',
                    'deactivated' => 'Modul "' . $moduleKey . '" wurde für User "' . ($targetUser->name ?? $targetUser->email) . '" im ' . $teamLabel . ' "' . $currentTeam->name . '" deaktiviert.',
                    'already_active' => 'Modul "' . $moduleKey . '" ist bereits aktiv für User "' . ($targetUser->name ?? $targetUser->email) . '" im ' . $teamLabel . ' "' . $currentTeam->name . '".',
                    'already_inactive' => 'Modul "' . $moduleKey . '" ist bereits inaktiv für User "' . ($targetUser->name ?? $targetUser->email) . '" im ' . $teamLabel . ' "' . $currentTeam->name . '".',
                },
                'module' => [
                    'key' => $moduleKey,
                    'title' => $module->title,
                    'scope_type' => $module->scope_type,
                ],
                'target_user' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name ?? null,
                    'email' => $targetUser->email ?? null,
                ],
                'team' => [
                    'id' => $currentTeam->id,
                    'name' => $currentTeam->name,
                ],
                'enabled' => $shouldBeEnabled,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Modul-Toggle: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'module', 'team', 'permission', 'toggle'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
            'confirmation_required' => false,
            'side_effects' => ['updates'],
            'related_tools' => ['core.modules.GET', 'core.modules.bulk_PUT', 'core.context.GET', 'core.teams.GET', 'core.team.switch'],
        ];
    }
}
