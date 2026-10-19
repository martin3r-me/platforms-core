<?php

namespace Platform\Core\Inbox\Support;

use Platform\Core\Inbox\Contracts\RecordingIngestion;

/**
 * Default-Naht: keine Aufnahme-Materialisierung verfügbar (No-op), solange kein
 * Quell-Modul eine echte RecordingIngestion-Impl bindet.
 */
class NullRecordingIngestion implements RecordingIngestion
{
    public function ingest(array $payload): ?int
    {
        return null;
    }
}
