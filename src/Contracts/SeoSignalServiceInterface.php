<?php

namespace Platform\Core\Contracts;

/**
 * Signal-Lese-Fläche des SEO-Moduls (Keystone).
 *
 * Fremdmodule speisen URLs über SeoUrlServiceInterface::register() ein und lesen
 * die gemessenen Signale hierüber zurück — nach Quelle (Read-Back) oder nach
 * Organisations-Knoten (Roll-up in den Baum). Das Signal-Bündel je URL enthält:
 * url/domain/path/is_own/status/priority, visibility, rankings[], keyword_demand[],
 * traffic{}, gsc{}, backlinks{count,top_domains[]}, on_page{}, recommendations[],
 * last_crawled_at.
 */
interface SeoSignalServiceInterface
{
    /**
     * Signal-Bündel für eine einzelne URL (oder null, wenn unbekannt).
     */
    public function getSignals(int $teamId, string $url): ?array;

    /**
     * Signal-Bündel aller an einem Organisations-Knoten hängenden URLs.
     *
     * @return array<int,array>  [url_id => Bündel]
     */
    public function getSignalsForNode(int $teamId, int $entityId): array;

    /**
     * Signal-Bündel der von einem Fremdmodul registrierten URLs — verschlüsselt
     * nach dessen source_id (der Konsument muss keine seo_url-IDs kennen).
     *
     * @param  int[]  $sourceIds
     * @return array<int,array>  [source_id => Bündel]
     */
    public function getSignalsBySource(int $teamId, string $sourceModule, array $sourceIds): array;
}
