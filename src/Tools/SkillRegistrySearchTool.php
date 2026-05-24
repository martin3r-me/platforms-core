<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\SkillRegistryService;

class SkillRegistrySearchTool implements ToolContract
{
    public function getName(): string
    {
        return 'skill_registry.SEARCH';
    }

    public function getDescription(): string
    {
        return 'Durchsucht die Skill-Registry nach verfügbaren Skills (Markdown-basierte Anleitungen in Obsidian-Vaults). Unterstützt natürlichsprachliche Queries (z.B. "Q-Meeting vorbereiten", "Quartalsbericht"). Skills sind wiederverwendbare Schritt-für-Schritt-Anleitungen mit definierten Tool-Abhängigkeiten.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Natürlichsprachliche Suchanfrage (z.B. "Q-Meeting vorbereiten", "Bericht erstellen").',
                ],
                'vault_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Spezifische Vault-ID durchsuchen (z.B. Team-Vault). Ohne Angabe werden alle Vaults des Users durchsucht.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Anzahl Ergebnisse (1-10, Standard: 5).',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'User muss authentifiziert sein.');
            }

            $query = trim((string) ($arguments['query'] ?? ''));
            $vaultId = $arguments['vault_id'] ?? null;
            $limit = min(10, max(1, (int) ($arguments['limit'] ?? 5)));

            if ($query === '') {
                return ToolResult::error('VALIDATION_ERROR', 'query darf nicht leer sein.');
            }

            $service = app(SkillRegistryService::class);

            // Team-Vault-ID: aus Argument, Team-Zuordnung, oder Config
            $teamVaultId = $vaultId
                ?? $service->resolveTeamVaultId($context->team ?? ($context->user->currentTeam ?? null))
                ?? config('platform.skills_vault_id');

            $results = $service->search($query, $context->user->id, $teamVaultId, $limit);

            return ToolResult::success([
                'skills' => $results,
                'count' => count($results),
                'query' => $query,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Skill-Suche: ' . $e->getMessage());
        }
    }
}
