<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Module;
use Platform\Core\Models\Team;

/**
 * LLM-Tool zum Aktivieren/Deaktivieren von Modulen pro Team.
 *
 * Ermöglicht es, ein Modul für ein gesamtes Team zu aktivieren oder deaktivieren.
 * Policy: Nur User mit OWNER-Rolle im aktuellen Team dürfen Team-Module
 * aktivieren oder deaktivieren (identisch zur UI-Berechtigung).
 *
 * Unterschied zu core.modules.PUT:
 * - core.modules.PUT: Aktiviert/deaktiviert Module für einzelne User im Team
 * - core.team_modules.PUT: Aktiviert/deaktiviert Module für das gesamte Team
 */
class ToggleTeamModuleTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.team_modules.PUT';
    }

    public function getDescription(): string
    {
        return 'Aktiviert oder deaktiviert ein Modul für das gesamte aktuelle Team. '
            . 'Nur Team-Owner dürfen diese Aktion ausführen (identisch zur UI-Berechtigung). '
            . 'Bei Parent-Modulen (scope_type=parent) wird die Zuweisung im Root-Team vorgenommen. '
            . 'Nutze "core.modules.GET" um verfügbare Module zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module_key' => [
                    'type' => 'string',
                    'description' => 'Key des Moduls (z.B. "planner", "crm", "okr"). Nutze "core.modules.GET" um verfügbare Module zu sehen.',
                ],
                'enabled' => [
                    'type' => 'boolean',
                    'description' => 'true = Modul für das Team aktivieren, false = Modul für das Team deaktivieren. Wenn nicht angegeben, wird der aktuelle Status umgeschaltet (Toggle).',
                ],
            ],
            'required' => ['module_key'],
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

            $moduleKey = $arguments['module_key'] ?? null;
            if (!is_string($moduleKey) || $moduleKey === '') {
                return ToolResult::error('VALIDATION_ERROR', 'module_key ist erforderlich. Nutze "core.modules.GET" um verfügbare Module zu sehen.');
            }

            $explicitEnabled = array_key_exists('enabled', $arguments) ? $arguments['enabled'] : null;

            // --- Aktuelles Team bestimmen (MCP Session Override berücksichtigen) ---

            $currentTeam = $context->team;
            if (!$currentTeam) {
                $currentTeam = $user->currentTeamRelation;
            }
            if (!$currentTeam) {
                return ToolResult::error('TEAM_ERROR', 'Kein aktuelles Team gefunden. Nutze "core.team.switch" um in ein Team zu wechseln.');
            }

            // --- Policy: Nur OWNER darf Team-Module aktivieren/deaktivieren (identisch zur UI) ---

            $membership = $currentTeam->users()->where('user_id', $user->id)->first();
            if (!$membership) {
                return ToolResult::error('ACCESS_DENIED', 'Du bist kein Mitglied des aktuellen Teams.');
            }

            $userRole = $membership->pivot->role ?? null;
            if ($userRole !== TeamRole::OWNER->value) {
                return ToolResult::error(
                    'ACCESS_DENIED',
                    'Nur Team-Owner dürfen Module für das Team aktivieren/deaktivieren. '
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

                if ($currentTeam->id !== $rootTeam->id) {
                    return ToolResult::error(
                        'SCOPE_ERROR',
                        'Das Modul "' . $moduleKey . '" ist ein Parent-Modul (scope_type=parent) und kann nur im Root-Team aktiviert/deaktiviert werden. '
                        . 'Aktuelles Team: "' . $currentTeam->name . '" (ID: ' . $currentTeam->id . '). '
                        . 'Root-Team: "' . $rootTeam->name . '" (ID: ' . $rootTeam->id . '). '
                        . 'Nutze "core.team.switch" um ins Root-Team zu wechseln.'
                    );
                }

                $targetTeam = $rootTeam;
            } else {
                $targetTeam = $currentTeam;
            }

            // --- Toggle-Logik ---

            $alreadyAssigned = $targetTeam->modules()
                ->where('module_id', $module->id)
                ->exists();

            // Bestimme gewünschten Zustand
            if ($explicitEnabled !== null) {
                $shouldBeEnabled = (bool) $explicitEnabled;
            } else {
                $shouldBeEnabled = !$alreadyAssigned;
            }

            $action = null;

            if ($shouldBeEnabled && !$alreadyAssigned) {
                $targetTeam->modules()->attach($module->id, [
                    'role' => null,
                    'enabled' => true,
                    'guard' => 'web',
                ]);
                $action = 'activated';
            } elseif (!$shouldBeEnabled && $alreadyAssigned) {
                $targetTeam->modules()->newPivotStatement()
                    ->where('modulable_id', $targetTeam->id)
                    ->where('modulable_type', Team::class)
                    ->where('module_id', $module->id)
                    ->delete();
                $action = 'deactivated';
            } else {
                $action = $shouldBeEnabled ? 'already_active' : 'already_inactive';
            }

            $teamLabel = $module->isRootScoped() ? 'Root-Team' : 'Team';

            return ToolResult::success([
                'action' => $action,
                'message' => match ($action) {
                    'activated' => 'Modul "' . $moduleKey . '" wurde für das ' . $teamLabel . ' "' . $targetTeam->name . '" aktiviert.',
                    'deactivated' => 'Modul "' . $moduleKey . '" wurde für das ' . $teamLabel . ' "' . $targetTeam->name . '" deaktiviert.',
                    'already_active' => 'Modul "' . $moduleKey . '" ist bereits aktiv für das ' . $teamLabel . ' "' . $targetTeam->name . '".',
                    'already_inactive' => 'Modul "' . $moduleKey . '" ist bereits inaktiv für das ' . $teamLabel . ' "' . $targetTeam->name . '".',
                },
                'module' => [
                    'key' => $moduleKey,
                    'title' => $module->title,
                    'scope_type' => $module->scope_type,
                ],
                'team' => [
                    'id' => $targetTeam->id,
                    'name' => $targetTeam->name,
                ],
                'enabled' => $shouldBeEnabled,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Team-Modul-Toggle: ' . $e->getMessage());
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
            'related_tools' => ['core.modules.GET', 'core.modules.PUT', 'core.context.GET', 'core.teams.GET', 'core.team.switch'],
        ];
    }
}
