<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CrmEngagementManagerInterface;

class NullCrmEngagementManager implements CrmEngagementManagerInterface
{
    public function createEngagement(array $data, array $contactIds = [], array $companyIds = []): ?string
    {
        return null;
    }
}
