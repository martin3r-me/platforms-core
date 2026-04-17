<?php

namespace Platform\Core\Tools\SemanticLayer;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Team;
use Platform\Core\SemanticLayer\Models\SemanticLayer;

/**
 * Owner-Check + Scope-Resolution für die Semantic-Layer-MCP-Tools.
 *
 * Alle Semantic-Layer-Tools sind owner-only (konsistent zur Admin-UI).
 * Der Trait kapselt die Owner-Prüfung und die Auflösung der gemeinsamen
 * Scope-Parameter (`scope`, `team_id`, `label`).
 */
trait AssertsOwnerAccess
{
    /**
     * Prüft, ob der aktuelle User OWNER im aktuellen Team-Kontext ist.
     *
     * Gibt `null` zurück, wenn der Check passt. Gibt ein `ToolResult::error(...)`
     * zurück, wenn der Check fehlschlägt — das Tool soll diesen Result direkt
     * weitergeben.
     */
    protected function assertOwner(ToolContext $context): ?ToolResult
    {
        $user = $context->user ?? null;
        if (!$user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
        }

        $currentTeam = $context->team ?? null;
        if (!$currentTeam && method_exists($user, 'getAttribute')) {
            $currentTeam = $user->currentTeamRelation ?? null;
        }
        if (!$currentTeam) {
            return ToolResult::error(
                'TEAM_ERROR',
                'Kein aktuelles Team gefunden. Nutze "core.team.switch" um in ein Team zu wechseln.'
            );
        }

        $membership = $currentTeam->users()->where('user_id', $user->id)->first();
        if (!$membership) {
            return ToolResult::error('ACCESS_DENIED', 'Du bist kein Mitglied des aktuellen Teams.');
        }

        $role = $membership->pivot->role ?? null;
        if ($role !== TeamRole::OWNER->value) {
            return ToolResult::error(
                'ACCESS_DENIED',
                'Nur Team-Owner dürfen den Semantic Layer verwalten. '
                . 'Deine aktuelle Rolle in diesem Team: ' . ($role ?? 'keine') . '.'
            );
        }

        return null;
    }

    /**
     * Löst die Scope-Parameter zu einem konkreten Tupel `[scope, team_id]` auf.
     *
     * @param array<string, mixed> $arguments
     * @return array{0: string, 1: ?int}|ToolResult
     *         Bei Erfolg ein Tupel, bei Fehler ein ToolResult, das das Tool direkt zurückgeben soll.
     */
    protected function resolveScope(array $arguments, ToolContext $context): array|ToolResult
    {
        $scope = $arguments['scope'] ?? SemanticLayer::SCOPE_GLOBAL;
        if (!in_array($scope, [SemanticLayer::SCOPE_GLOBAL, SemanticLayer::SCOPE_TEAM], true)) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'Ungültiger scope: "' . (string) $scope . '". Erlaubt: "global" oder "team".'
            );
        }

        if ($scope === SemanticLayer::SCOPE_GLOBAL) {
            return [SemanticLayer::SCOPE_GLOBAL, null];
        }

        // scope=team
        $teamId = $arguments['team_id'] ?? null;

        if ($teamId === null || $teamId === '' || $teamId === 0) {
            // Fallback: aktueller Team-Kontext
            $currentTeam = $context->team ?? null;
            if (!$currentTeam && method_exists($context->user ?? null, 'getAttribute')) {
                $currentTeam = $context->user->currentTeamRelation ?? null;
            }
            if (!$currentTeam) {
                return ToolResult::error(
                    'MISSING_TEAM_CONTEXT',
                    'scope=team verlangt entweder einen "team_id"-Parameter oder einen aktiven Team-Kontext (siehe "core.team.switch").'
                );
            }
            return [SemanticLayer::SCOPE_TEAM, (int) $currentTeam->id];
        }

        $teamId = (int) $teamId;
        if ($teamId <= 0) {
            return ToolResult::error('VALIDATION_ERROR', 'team_id muss eine positive Zahl sein.');
        }

        if (!Team::find($teamId)) {
            return ToolResult::error('TEAM_NOT_FOUND', 'Team mit ID ' . $teamId . ' wurde nicht gefunden.');
        }

        return [SemanticLayer::SCOPE_TEAM, $teamId];
    }

    /**
     * Löst einen Layer auf via `layer_id` (direkt) ODER `scope + label`.
     *
     * @param array<string, mixed> $arguments
     * @return SemanticLayer|ToolResult
     */
    protected function resolveLayer(array $arguments, ToolContext $context): SemanticLayer|ToolResult
    {
        // Direkte ID
        $layerId = $arguments['layer_id'] ?? null;
        if ($layerId !== null && $layerId !== '' && $layerId !== 0) {
            $layer = SemanticLayer::find((int) $layerId);
            if (!$layer) {
                return ToolResult::error('LAYER_NOT_FOUND', 'Layer mit ID ' . $layerId . ' wurde nicht gefunden.');
            }
            return $layer;
        }

        // Scope + Label
        $scopeResult = $this->resolveScope($arguments, $context);
        if ($scopeResult instanceof ToolResult) {
            return $scopeResult;
        }
        [$scope, $teamId] = $scopeResult;

        $label = $arguments['label'] ?? SemanticLayer::LABEL_LEITBILD;
        if (!is_string($label) || $label === '') {
            $label = SemanticLayer::LABEL_LEITBILD;
        }

        $layer = SemanticLayer::where('scope_type', $scope)
            ->where('scope_id', $teamId)
            ->where('label', $label)
            ->first();

        if (!$layer) {
            return ToolResult::error(
                'LAYER_NOT_FOUND',
                'Es existiert noch kein Semantic-Layer für scope=' . $scope
                . ($scope === SemanticLayer::SCOPE_TEAM ? ', team_id=' . $teamId : '')
                . ', label="' . $label . '"'
                . '. Lege einen mit "core.semantic_layer.versions.POST" an.'
            );
        }

        return $layer;
    }
}
