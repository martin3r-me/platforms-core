<?php

return [
    // WebDAV-/CalDAV-/CardDAV-Infrastruktur (siehe modules/crm/docs/dav-core-extraction.md)
    'enabled' => env('DAV_ENABLED', true),

    // Basis-URL-Segment des DAV-Servers. Default 'crm/dav' erhält die bestehende
    // Live-URL (bereits eingerichtete Geräte-Abos bleiben gültig).
    'path' => env('DAV_PATH', 'crm/dav'),

    // Gültigkeit neuer Abo-Secrets in Tagen; null = unbegrenzt.
    'secret_ttl_days' => env('DAV_SECRET_TTL_DAYS'),
];
