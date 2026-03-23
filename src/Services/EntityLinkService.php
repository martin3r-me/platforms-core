<?php

namespace Platform\Core\Services;

use Illuminate\Support\Collection;
use Platform\Core\Models\CoreEntityLink;

class EntityLinkService
{
    /**
     * Erstellt einen Link zwischen zwei Entitäten (idempotent via firstOrCreate).
     */
    public function link(
        int $teamId,
        string $sourceType,
        int $sourceId,
        string $targetType,
        int $targetId,
        string $linkType = 'related',
        ?int $sortOrder = null,
        ?array $metadata = null,
    ): CoreEntityLink {
        return CoreEntityLink::firstOrCreate(
            [
                'team_id' => $teamId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'link_type' => $linkType,
            ],
            [
                'sort_order' => $sortOrder,
                'metadata' => $metadata,
            ],
        );
    }

    /**
     * Löscht einen Link zwischen zwei Entitäten.
     * Prüft beide Richtungen (source↔target und target↔source).
     */
    public function unlink(
        int $teamId,
        string $sourceType,
        int $sourceId,
        string $targetType,
        int $targetId,
        string $linkType = 'related',
    ): bool {
        $deleted = CoreEntityLink::where('team_id', $teamId)
            ->where('link_type', $linkType)
            ->where(function ($q) use ($sourceType, $sourceId, $targetType, $targetId) {
                $q->where(function ($q2) use ($sourceType, $sourceId, $targetType, $targetId) {
                    $q2->where('source_type', $sourceType)
                        ->where('source_id', $sourceId)
                        ->where('target_type', $targetType)
                        ->where('target_id', $targetId);
                })->orWhere(function ($q2) use ($sourceType, $sourceId, $targetType, $targetId) {
                    $q2->where('source_type', $targetType)
                        ->where('source_id', $targetId)
                        ->where('target_type', $sourceType)
                        ->where('target_id', $sourceId);
                });
            })
            ->delete();

        return $deleted > 0;
    }

    /**
     * Gibt alle verlinkten Entitäten zurück (bidirektional).
     *
     * @return Collection<int, CoreEntityLink>
     */
    public function getLinked(
        int $teamId,
        string $entityType,
        int $entityId,
        ?string $linkedType = null,
        ?string $linkType = null,
    ): Collection {
        $query = CoreEntityLink::forTeam($teamId)
            ->forEntity($entityType, $entityId);

        if ($linkType !== null) {
            $query->ofLinkType($linkType);
        }

        $links = $query->get();

        if ($linkedType !== null) {
            $links = $links->filter(function (CoreEntityLink $link) use ($entityType, $entityId, $linkedType) {
                $other = $link->getLinkedEntity($entityType, $entityId);
                return $other['type'] === $linkedType;
            })->values();
        }

        return $links;
    }

    /**
     * Gibt nur die IDs der verlinkten Entitäten zurück.
     *
     * @return array<int>
     */
    public function getLinkedIds(
        int $teamId,
        string $entityType,
        int $entityId,
        ?string $linkedType = null,
        ?string $linkType = null,
    ): array {
        return $this->getLinked($teamId, $entityType, $entityId, $linkedType, $linkType)
            ->map(fn (CoreEntityLink $link) => $link->getLinkedEntity($entityType, $entityId)['id'])
            ->all();
    }

    /**
     * Synchronisiert Links: Fügt fehlende hinzu, entfernt überzählige.
     */
    public function syncLinks(
        int $teamId,
        string $entityType,
        int $entityId,
        string $linkedType,
        array $targetIds,
        string $linkType = 'related',
    ): void {
        $currentIds = $this->getLinkedIds($teamId, $entityType, $entityId, $linkedType, $linkType);

        // Zu entfernende Links
        $toRemove = array_diff($currentIds, $targetIds);
        foreach ($toRemove as $removeId) {
            $this->unlink($teamId, $entityType, $entityId, $linkedType, $removeId, $linkType);
        }

        // Neue Links hinzufügen
        $toAdd = array_diff($targetIds, $currentIds);
        foreach ($toAdd as $addId) {
            $this->link($teamId, $entityType, $entityId, $linkedType, $addId, $linkType);
        }
    }

    /**
     * Entfernt alle Links einer Entity (z.B. bei Löschung).
     */
    public function unlinkAll(int $teamId, string $entityType, int $entityId): int
    {
        return CoreEntityLink::forTeam($teamId)
            ->forEntity($entityType, $entityId)
            ->delete();
    }
}
