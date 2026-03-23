<?php

namespace Platform\Core\Tools\EntityLinks;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreEntityLink;
use Platform\Core\Services\EntityLinkService;

/**
 * core.entity_links.GET
 *
 * Listet alle Entity-Links einer Entität auf (bidirektional).
 */
class ListEntityLinksTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private readonly EntityLinkService $entityLinkService,
    ) {}

    public function getName(): string
    {
        return 'core.entity_links.GET';
    }

    public function getDescription(): string
    {
        return 'GET /core/entity_links - Listet alle Verknüpfungen einer Entität auf (bidirektional). Parameter: entity_type, entity_id (required), linked_type, link_type (optional). Gibt Links mit Source/Target-Details und Gesamtanzahl zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_type' => [
                    'type' => 'string',
                    'description' => 'Typ der Entität, deren Links abgefragt werden (z.B. "project", "contact"). ERFORDERLICH.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Entität. ERFORDERLICH.',
                ],
                'linked_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Nur Links zu diesem Entitäts-Typ zurückgeben (z.B. "contact").',
                ],
                'link_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Nur Links dieses Typs zurückgeben (z.B. "related").',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Wird automatisch aus dem Kontext übernommen, wenn nicht angegeben.',
                ],
            ],
            'required' => ['entity_type', 'entity_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Team-Kontext vorhanden. Bitte team_id angeben oder Team-Kontext setzen.');
            }

            $entityType = trim((string) ($arguments['entity_type'] ?? ''));
            $entityId = $arguments['entity_id'] ?? null;

            if ($entityType === '' || $entityId === null) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_type und entity_id sind erforderlich.');
            }

            $entityId = (int) $entityId;
            if ($entityId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id muss eine positive Zahl sein.');
            }

            $linkedType = isset($arguments['linked_type']) ? trim((string) $arguments['linked_type']) : null;
            $linkType = isset($arguments['link_type']) ? trim((string) $arguments['link_type']) : null;

            if ($linkedType === '') {
                $linkedType = null;
            }
            if ($linkType === '') {
                $linkType = null;
            }

            $links = $this->entityLinkService->getLinked(
                teamId: (int) $teamId,
                entityType: $entityType,
                entityId: $entityId,
                linkedType: $linkedType,
                linkType: $linkType,
            );

            $linksData = $links->map(function (CoreEntityLink $link) use ($entityType, $entityId) {
                $linked = $link->getLinkedEntity($entityType, $entityId);

                return [
                    'id' => $link->id,
                    'uuid' => $link->uuid,
                    'source_type' => $link->source_type,
                    'source_id' => $link->source_id,
                    'target_type' => $link->target_type,
                    'target_id' => $link->target_id,
                    'linked_type' => $linked['type'],
                    'linked_id' => $linked['id'],
                    'link_type' => $link->link_type,
                    'sort_order' => $link->sort_order,
                    'metadata' => $link->metadata,
                    'created_at' => $link->created_at?->toIso8601String(),
                ];
            })->values()->all();

            return ToolResult::success([
                'entity' => "{$entityType}#{$entityId}",
                'links' => $linksData,
                'total' => count($linksData),
                'message' => count($linksData) . ' Verknüpfung(en) gefunden.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen der Entity-Links: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['core', 'entity-link', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
