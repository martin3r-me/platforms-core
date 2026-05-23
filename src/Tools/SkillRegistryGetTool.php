<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\SkillRegistryService;

class SkillRegistryGetTool implements ToolContract
{
    public function getName(): string
    {
        return 'skill_registry.GET';
    }

    public function getDescription(): string
    {
        return 'Gibt detaillierte Informationen zu einem einzelnen Skill zurück, inklusive der vollständigen Markdown-Anleitung (body_markdown). Nutze skill_registry.SEARCH um den Code zu finden, dann dieses Tool für die volle Anleitung.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => [
                    'type' => 'string',
                    'description' => 'Skill-Code (z.B. "SKILL-QMEETING-KURZBERICHT"). Verwende skill_registry.SEARCH um Codes zu finden.',
                ],
                'vault_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Spezifische Vault-ID durchsuchen (z.B. Team-Vault). Ohne Angabe werden alle Vaults des Users durchsucht.',
                ],
                'include_body' => [
                    'type' => 'boolean',
                    'description' => 'Soll der vollständige Markdown-Body enthalten sein? Standard: true.',
                ],
            ],
            'required' => ['code'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'User muss authentifiziert sein.');
            }

            $code = trim((string) ($arguments['code'] ?? ''));
            $vaultId = $arguments['vault_id'] ?? null;
            $includeBody = $arguments['include_body'] ?? true;

            if ($code === '') {
                return ToolResult::error('VALIDATION_ERROR', 'code darf nicht leer sein.');
            }

            // Team-Vault-ID: aus Argument, Team-Zuordnung, oder Config
            $teamVaultId = $vaultId
                ?? $this->resolveTeamVaultId($context)
                ?? config('platform.skills_vault_id');

            $service = app(SkillRegistryService::class);
            $result = $service->get($code, $context->user->id, $teamVaultId);

            if (!$result) {
                return ToolResult::error('NOT_FOUND', "Skill '{$code}' nicht gefunden. Nutze skill_registry.SEARCH um verfügbare Skills zu finden.");
            }

            if (!$includeBody) {
                unset($result['body_markdown']);
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen des Skills: ' . $e->getMessage());
        }
    }

    /**
     * Findet die Vault-ID des Team-Vaults über die team_id-Spalte.
     */
    private function resolveTeamVaultId(ToolContext $context): ?int
    {
        $team = $context->team ?? ($context->user?->currentTeam ?? null);
        if (!$team) {
            return null;
        }

        $vault = ObsidianVault::where('team_id', $team->id)->first();
        return $vault?->id;
    }
}
