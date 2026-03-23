<?php

namespace Platform\Core\Tools\EntityLinks;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreEntityLink;
use Platform\Core\Services\EntityLinkService;

/**
 * core.entity_links.DELETE
 *
 * Löscht einen Entity-Link per ID oder per Source/Target-Kombination.
 */
class UnlinkEntitiesTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private readonly EntityLinkService $entityLinkService,
    ) {}

    public function getName(): string
    {
        return 'core.entity_links.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /core/entity_links - Löscht eine Verknüpfung zwischen zwei Entitäten. Entweder per link_id (direkt) oder per source_type, source_id, target_type, target_id + optional link_type.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'link_id' => [
                    'type' => 'integer',
                    'description' => 'Direkte ID des Links. Wenn angegeben, werden source/target-Parameter ignoriert.',
                ],
                'source_type' => [
                    'type' => 'string',
                    'description' => 'Typ der Quell-Entität. Erforderlich wenn link_id nicht angegeben.',
                ],
                'source_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Quell-Entität. Erforderlich wenn link_id nicht angegeben.',
                ],
                'target_type' => [
                    'type' => 'string',
                    'description' => 'Typ der Ziel-Entität. Erforderlich wenn link_id nicht angegeben.',
                ],
                'target_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Ziel-Entität. Erforderlich wenn link_id nicht angegeben.',
                ],
                'link_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Art der Verknüpfung. Standard: "related".',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Wird automatisch aus dem Kontext übernommen, wenn nicht angegeben.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Team-Kontext vorhanden. Bitte team_id angeben oder Team-Kontext setzen.');
            }

            $teamId = (int) $teamId;

            // Option A: Direkte Löschung per link_id
            if (isset($arguments['link_id'])) {
                $linkId = (int) $arguments['link_id'];
                if ($linkId <= 0) {
                    return ToolResult::error('VALIDATION_ERROR', 'link_id muss eine positive Zahl sein.');
                }

                $link = CoreEntityLink::where('id', $linkId)
                    ->where('team_id', $teamId)
                    ->first();

                if (!$link) {
                    return ToolResult::error('NOT_FOUND', "Link mit ID {$linkId} nicht gefunden (im aktuellen Team).");
                }

                $info = "{$link->source_type}#{$link->source_id} ↔ {$link->target_type}#{$link->target_id}";
                $link->delete();

                return ToolResult::success([
                    'deleted_link_id' => $linkId,
                    'message' => "Link #{$linkId} gelöscht ({$info}).",
                ]);
            }

            // Option B: Löschung per Source/Target-Kombination
            $sourceType = trim((string) ($arguments['source_type'] ?? ''));
            $sourceId = $arguments['source_id'] ?? null;
            $targetType = trim((string) ($arguments['target_type'] ?? ''));
            $targetId = $arguments['target_id'] ?? null;

            if ($sourceType === '' || $sourceId === null || $targetType === '' || $targetId === null) {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder link_id oder source_type, source_id, target_type und target_id angeben.');
            }

            $sourceId = (int) $sourceId;
            $targetId = (int) $targetId;

            if ($sourceId <= 0 || $targetId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'source_id und target_id müssen positive Zahlen sein.');
            }

            $linkType = trim((string) ($arguments['link_type'] ?? 'related'));

            $deleted = $this->entityLinkService->unlink(
                teamId: $teamId,
                sourceType: $sourceType,
                sourceId: $sourceId,
                targetType: $targetType,
                targetId: $targetId,
                linkType: $linkType,
            );

            if (!$deleted) {
                return ToolResult::error('NOT_FOUND', "Kein Link gefunden zwischen {$sourceType}#{$sourceId} und {$targetType}#{$targetId} (link_type: {$linkType}).");
            }

            return ToolResult::success([
                'message' => "Link gelöscht: {$sourceType}#{$sourceId} ↔ {$targetType}#{$targetId} ({$linkType}).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Entity-Links: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'entity-link', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
