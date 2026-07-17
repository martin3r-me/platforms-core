<?php

namespace Platform\Core\Dav;

use Platform\Core\Models\DavSubscription;
use Sabre\DAV\Exception\NotAuthenticated;

/**
 * Geteilter Request-Kontext zwischen Auth- und Modul-Backends.
 *
 * Die Backends werden gebaut, bevor die Authentifizierung gelaufen ist. Das
 * {@see TokenAuthBackend} schreibt das aufgelöste Abo hier hinein; die Modul-
 * Backends lesen es lazy, sobald sabre ihre Methoden aufruft (nach der Auth).
 *
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
class DavContext
{
    private ?DavSubscription $subscription = null;

    public function setSubscription(DavSubscription $subscription): void
    {
        $this->subscription = $subscription;
    }

    public function hasSubscription(): bool
    {
        return $this->subscription !== null;
    }

    public function subscription(): DavSubscription
    {
        if ($this->subscription === null) {
            throw new NotAuthenticated('DAV: kein authentifiziertes Abo im Kontext.');
        }

        return $this->subscription;
    }
}
