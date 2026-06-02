<?php

namespace Platform\Core\Contracts;

interface CrmEngagementManagerInterface
{
    /**
     * Erstellt ein Engagement und verknüpft Kontakte/Firmen.
     *
     * @param array $data  Keys: type, title, body?, status?, priority?,
     *                     scheduled_at?, ended_at?, completed_at?, metadata?,
     *                     team_id (required), owned_by_user_id?, created_by_user_id?
     * @param int[] $contactIds
     * @param int[] $companyIds
     * @return ?string  UUID des Engagements, oder null (noop / Fehler)
     */
    public function createEngagement(array $data, array $contactIds = [], array $companyIds = []): ?string;
}
