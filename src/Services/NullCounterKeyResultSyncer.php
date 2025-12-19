<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CounterKeyResultSyncer;

class NullCounterKeyResultSyncer implements CounterKeyResultSyncer
{
    public function syncAll(bool $dryRun = false): int
    {
        return 0;
    }
}


