<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Module;
use Platform\Core\Models\User;

/**
 * LLM-Tool zum Bulk-Aktivieren/Deaktivieren mehrerer Module pro User.
 *
 * Ermöglicht es, mehrere Module in einem einzigen Aufruf für einen User
 * freizuschalten oder zu entziehen. Jedes Modul wird einzeln validiert,
 * sodass ein klares Fehler-Reporting pro Modul-Key erfolgt.
 *
 * Policy: Nur User mit OWNER-Rolle im aktuellen Team dürfen Module
 * für Team-Mitglieder aktivieren oder deaktivieren.
 */
class BulkToggleModuleTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.modules.bulk_PUT';
    }

    public function getDescription(): string
    {
        return 'Aktiviert oder deaktiviert mehrere Module gleichzeitig für einen User im aktuellen Team. '
            . 'Akzeptiert eine Liste von Modul-Keys und verarbeitet jedes Modul einzeln mit detailliertem Fehler-Reporting. '
            . 'Nur Team-Owner dürfen diese Aktion ausführen (identisch zur UI-Berechtigung). '
            . 'Nutze "core.modules.GET" um verfügbare Module zu sehen und "core.context.GET" mit include_members=true um Team-Mitglieder zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Users, für den die Module aktiviert/deaktiviert werden sollen. Der User muss Mitglied des aktuellen Teams sein.',
                ],
                'module_keys' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                    'description' => 'Liste der Modul-Keys (z.B. ["planner", "crm", "okr"]). Nutze "core.modules.GET" um verfügbare Module zu sehen.',
                ],
                'enabled' => [
                    'type' => 'boolean',
                    'description' => 'true = Module aktivieren, false = Module deaktivieren. Wenn nicht angegeben, wird der aktuelle Status pro Modul umgeschaltet (Toggle).',
                ],
            ],
            'required' => ['user_id', 'module_keys'],
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

            $moduleKeys = $arguments['module_keys'] ?? null;
            if (!is_array($moduleKeys) || count($moduleKeys) === 0) {
                return ToolResult::error('VALIDATION_ERROR', 'module_keys ist erforderlich und muss ein nicht-leeres Array von Modul-Keys sein.');
            }

            // Duplikate entfernen, nur Strings zulassen
            $moduleKeys = array_values(array_unique(array_filter($moduleKeys, 'is_string')));
            if (count($moduleKeys) === 0) {
                return ToolResult::error('VALIDATION_ERROR', 'module_keys muss mindestens einen gültigen Modul-Key (String) enthalten.');
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

            // --- Ziel-User laden und prüfen ---

            $targetUser = User::find($targetUserId);
            if (!$targetUser) {
                return ToolResult::error('USER_NOT_FOUND', 'Der User mit ID ' . $targetUserId . ' wurde nicht gefunden.');
            }

            $targetIsMember = $currentTeam->users()->where('user_id', $targetUser->id)->exists();
            if (!$targetIsMember) {
                return ToolResult::error(
                    'USER_NOT_IN_TEAM',
                    'Der User "' . ($targetUser->name ?? $targetUser->email) . '" (ID: ' . $targetUserId . ') ist kein Mitglied des Teams "' . $currentTeam->name . '". '
                    . 'Nutze "core.context.GET" mit include_members=true um alle Team-Mitglieder zu sehen.'
                );
            }

            // --- Bulk-Verarbeitung: Jedes Modul einzeln ---

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($moduleKeys as $moduleKey) {
                $result = $this->processModule(
                    $moduleKey,
                    $targetUser,
                    $currentTeam,
                    $explicitEnabled
                );

                $results[] = $result;

                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }

            $targetUserLabel = $targetUser->name ?? $targetUser->email;

            return ToolResult::success([
                'message' => $successCount . ' von ' . count($moduleKeys) . ' Modulen erfolgreich verarbeitet'
                    . ($errorCount > 0 ? ' (' . $errorCount . ' Fehler)' : '')
                    . ' für User "' . $targetUserLabel . '" im Team "' . $currentTeam->name . '".',
                'target_user' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name ?? null,
                    'email' => $targetUser->email ?? null,
                ],
                'team' => [
                    'id' => $currentTeam->id,
                    'name' => $currentTeam->name,
                ],
                'summary' => [
                    'total' => count($moduleKeys),
                    'successful' => $successCount,
                    'failed' => $errorCount,
                ],
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Modul-Toggle: ' . $e->getMessage());
        }
    }

    /**
     * Verarbeitet ein einzelnes Modul im Bulk-Kontext.
     */
    private function processModule(
        string $moduleKey,
        User $targetUser,
        object $currentTeam,
        ?bool $explicitEnabled
    ): array {
        try {
            if ($moduleKey === '') {
                return [
                    'module_key' => $moduleKey,
                    'success' => false,
                    'error' => 'Leerer Modul-Key.',
                    'error_code' => 'VALIDATION_ERROR',
                ];
            }

            // --- Modul laden ---

            $module = Module::where('key', $moduleKey)->first();
            if (!$module) {
                return [
                    'module_key' => $moduleKey,
                    'success' => false,
                    'error' => 'Das Modul "' . $moduleKey . '" wurde nicht gefunden.',
                    'error_code' => 'MODULE_NOT_FOUND',
                ];
            }

            // --- Scope: Root-Team vs. aktuelles Team ---

            if ($module->isRootScoped()) {
                $rootTeam = $currentTeam->getRootTeam();

                if ($currentTeam->id !== $rootTeam->id) {
                    return [
                        'module_key' => $moduleKey,
                        'success' => false,
                        'error' => 'Das Modul "' . $moduleKey . '" ist ein Parent-Modul (scope_type=parent) und kann nur im Root-Team vergeben werden. '
                            . 'Aktuelles Team: "' . $currentTeam->name . '" (ID: ' . $currentTeam->id . '). '
                            . 'Root-Team: "' . $rootTeam->name . '" (ID: ' . $rootTeam->id . ').',
                        'error_code' => 'SCOPE_ERROR',
                    ];
                }

                $teamId = $rootTeam->id;
            } else {
                $teamId = $currentTeam->id;
            }

            // --- Toggle-Logik (identisch zu ToggleModuleTool) ---

            $alreadyAssigned = $targetUser->modules()
                ->where('module_id', $module->id)
                ->wherePivot('team_id', $teamId)
                ->exists();

            if ($explicitEnabled !== null) {
                $shouldBeEnabled = (bool) $explicitEnabled;
            } else {
                $shouldBeEnabled = !$alreadyAssigned;
            }

            $action = null;

            if ($shouldBeEnabled && !$alreadyAssigned) {
                $targetUser->modules()->attach($module->id, [
                    'role' => null,
                    'enabled' => true,
                    'guard' => 'web',
                    'team_id' => $teamId,
                ]);
                $action = 'activated';
            } elseif (!$shouldBeEnabled && $alreadyAssigned) {
                $targetUser->modules()->newPivotStatement()
                    ->where('modulable_id', $targetUser->id)
                    ->where('modulable_type', User::class)
                    ->where('module_id', $module->id)
                    ->where('team_id', $teamId)
                    ->delete();
                $action = 'deactivated';
            } else {
                $action = $shouldBeEnabled ? 'already_active' : 'already_inactive';
            }

            $teamLabel = $module->isRootScoped() ? 'Root-Team' : 'Team';

            return [
                'module_key' => $moduleKey,
                'success' => true,
                'action' => $action,
                'message' => match ($action) {
                    'activated' => 'Modul "' . $moduleKey . '" im ' . $teamLabel . ' aktiviert.',
                    'deactivated' => 'Modul "' . $moduleKey . '" im ' . $teamLabel . ' deaktiviert.',
                    'already_active' => 'Modul "' . $moduleKey . '" ist bereits aktiv im ' . $teamLabel . '.',
                    'already_inactive' => 'Modul "' . $moduleKey . '" ist bereits inaktiv im ' . $teamLabel . '.',
                },
                'module' => [
                    'key' => $moduleKey,
                    'title' => $module->title,
                    'scope_type' => $module->scope_type,
                ],
                'enabled' => $shouldBeEnabled,
            ];
        } catch (\Throwable $e) {
            return [
                'module_key' => $moduleKey,
                'success' => false,
                'error' => 'Fehler: ' . $e->getMessage(),
                'error_code' => 'EXECUTION_ERROR',
            ];
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'module', 'team', 'permission', 'toggle', 'bulk'],
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
