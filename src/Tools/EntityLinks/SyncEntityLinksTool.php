<?php

namespace Platform\Core\Tools\EntityLinks;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\EntityLinkService;

/**
 * core.entity_links.SYNC
 *
 * Synchronisiert Entity-Links: Fügt fehlende hinzu, entfernt überzählige.
 */
class SyncEntityLinksTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private readonly EntityLinkService $entityLinkService,
    ) {}

    public function getName(): string
    {
        return 'core.entity_links.SYNC';
    }

    public function getDescription(): string
    {
        return 'SYNC /core/entity_links - Synchronisiert Verknüpfungen einer Entität zu einem bestimmten Ziel-Typ. Fügt fehlende Links hinzu und entfernt überzählige. Parameter: entity_type, entity_id, linked_type, target_ids (required), link_type (optional, default "related").';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_type' => [
                    'type' => 'string',
                    'description' => 'Typ der Ausgangs-Entität (z.B. "project"). ERFORDERLICH.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Ausgangs-Entität. ERFORDERLICH.',
                ],
                'linked_type' => [
                    'type' => 'string',
                    'description' => 'Typ der zu synchronisierenden Ziel-Entitäten (z.B. "contact"). ERFORDERLICH.',
                ],
                'target_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von Ziel-IDs. Leeres Array entfernt alle Links dieses Typs. ERFORDERLICH.',
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
            'required' => ['entity_type', 'entity_id', 'linked_type', 'target_ids'],
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

            $entityType = trim((string) ($arguments['entity_type'] ?? ''));
            $entityId = $arguments['entity_id'] ?? null;
            $linkedType = trim((string) ($arguments['linked_type'] ?? ''));

            if ($entityType === '' || $entityId === null) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_type und entity_id sind erforderlich.');
            }
            if ($linkedType === '') {
                return ToolResult::error('VALIDATION_ERROR', 'linked_type ist erforderlich.');
            }

            $entityId = (int) $entityId;
            if ($entityId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id muss eine positive Zahl sein.');
            }

            $targetIds = $arguments['target_ids'] ?? null;
            if (!is_array($targetIds)) {
                if (is_string($targetIds)) {
                    $targetIds = json_decode($targetIds, true);
                }
                if (!is_array($targetIds)) {
                    return ToolResult::error('VALIDATION_ERROR', 'target_ids muss ein Array von IDs sein.');
                }
            }

            $targetIds = array_map('intval', $targetIds);
            $targetIds = array_filter($targetIds, fn (int $id) => $id > 0);
            $targetIds = array_values(array_unique($targetIds));

            $linkType = trim((string) ($arguments['link_type'] ?? 'related'));

            // Vorher-Zustand ermitteln
            $beforeIds = $this->entityLinkService->getLinkedIds(
                teamId: $teamId,
                entityType: $entityType,
                entityId: $entityId,
                linkedType: $linkedType,
                linkType: $linkType,
            );

            $this->entityLinkService->syncLinks(
                teamId: $teamId,
                entityType: $entityType,
                entityId: $entityId,
                linkedType: $linkedType,
                targetIds: $targetIds,
                linkType: $linkType,
            );

            $added = array_values(array_diff($targetIds, $beforeIds));
            $removed = array_values(array_diff($beforeIds, $targetIds));

            return ToolResult::success([
                'entity' => "{$entityType}#{$entityId}",
                'linked_type' => $linkedType,
                'link_type' => $linkType,
                'added_count' => count($added),
                'removed_count' => count($removed),
                'added_ids' => $added,
                'removed_ids' => $removed,
                'final_ids' => $targetIds,
                'message' => count($added) . ' hinzugefügt, ' . count($removed) . ' entfernt. ' . count($targetIds) . ' Verknüpfung(en) aktiv.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Synchronisieren der Entity-Links: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'entity-link', 'sync'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
