<?php

return [
    // WebDAV-/CalDAV-/CardDAV-Infrastruktur (siehe modules/crm/docs/dav-core-extraction.md)
    'enabled' => env('DAV_ENABLED', true),

    // Basis-URL-Segment des DAV-Servers (generisch, modulübergreifend).
    'path' => env('DAV_PATH', 'dav'),

    // Gültigkeit neuer Abo-Secrets in Tagen; null = unbegrenzt.
    'secret_ttl_days' => env('DAV_SECRET_TTL_DAYS'),
];
