<?php

namespace Platform\Core\Tools\EntityLinks;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\EntityLinkService;

/**
 * core.entity_links.POST
 *
 * Erstellt einen Link zwischen zwei Entitäten (idempotent).
 */
class LinkEntitiesTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private readonly EntityLinkService $entityLinkService,
    ) {}

    public function getName(): string
    {
        return 'core.entity_links.POST';
    }

    public function getDescription(): string
    {
        return 'POST /core/entity_links - Erstellt einen Link zwischen zwei Entitäten (idempotent). Parameter: source_type, source_id, target_type, target_id (required), link_type (default "related"), sort_order, metadata (optional). Entity-Typen sind z.B. "project", "contact", "organization".';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'source_type' => [
                    'type' => 'string',
                    'description' => 'Typ der Quell-Entität (z.B. "project", "contact", "organization"). ERFORDERLICH.',
                ],
                'source_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Quell-Entität. ERFORDERLICH.',
                ],
                'target_type' => [
                    'type' => 'string',
                    'description' => 'Typ der Ziel-Entität (z.B. "project", "contact", "organization"). ERFORDERLICH.',
                ],
                'target_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Ziel-Entität. ERFORDERLICH.',
                ],
                'link_type' => [
                    'type' => 'string',
                    'description' => 'Art der Verknüpfung. Standard: "related".',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierreihenfolge des Links.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Zusätzliche Metadaten als JSON-Objekt.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Wird automatisch aus dem Kontext übernommen, wenn nicht angegeben.',
                ],
            ],
            'required' => ['source_type', 'source_id', 'target_type', 'target_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Team-Kontext vorhanden. Bitte team_id angeben oder Team-Kontext setzen.');
            }

            $sourceType = trim((string) ($arguments['source_type'] ?? ''));
            $sourceId = $arguments['source_id'] ?? null;
            $targetType = trim((string) ($arguments['target_type'] ?? ''));
            $targetId = $arguments['target_id'] ?? null;

            if ($sourceType === '' || $sourceId === null) {
                return ToolResult::error('VALIDATION_ERROR', 'source_type und source_id sind erforderlich.');
            }
            if ($targetType === '' || $targetId === null) {
                return ToolResult::error('VALIDATION_ERROR', 'target_type und target_id sind erforderlich.');
            }

            $sourceId = (int) $sourceId;
            $targetId = (int) $targetId;

            if ($sourceId <= 0 || $targetId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'source_id und target_id müssen positive Zahlen sein.');
            }

            $linkType = trim((string) ($arguments['link_type'] ?? 'related'));
            $sortOrder = isset($arguments['sort_order']) ? (int) $arguments['sort_order'] : null;
            $metadata = $arguments['metadata'] ?? null;

            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true);
            }

            $link = $this->entityLinkService->link(
                teamId: (int) $teamId,
                sourceType: $sourceType,
                sourceId: $sourceId,
                targetType: $targetType,
                targetId: $targetId,
                linkType: $linkType,
                sortOrder: $sortOrder,
                metadata: $metadata,
            );

            return ToolResult::success([
                'link' => [
                    'id' => $link->id,
                    'uuid' => $link->uuid,
                    'source_type' => $link->source_type,
                    'source_id' => $link->source_id,
                    'target_type' => $link->target_type,
                    'target_id' => $link->target_id,
                    'link_type' => $link->link_type,
                    'sort_order' => $link->sort_order,
                    'metadata' => $link->metadata,
                    'created_at' => $link->created_at?->toIso8601String(),
                ],
                'message' => "Link erstellt: {$sourceType}#{$sourceId} ↔ {$targetType}#{$targetId} ({$linkType}).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Entity-Links: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'entity-link', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
