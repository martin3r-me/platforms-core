<?php

namespace Platform\Core\Inbox\Support;

use Platform\Core\Inbox\Contracts\SourceDetail;

/**
 * Sammelstelle für SourceDetail-Provider je Kanal. Quell-Module registrieren
 * hier beim Boot ihren Provider; der Triage-Kern fragt beim Öffnen eines Items
 * „detailFor(channel, id)" und bekommt das normalisierte Detail-Array.
 *
 * Als Singleton in Core gebunden. Leere Registry = Reading-Pane fällt auf die
 * gecachten Listen-Felder zurück (defensiv, nichts bricht).
 */
class SourceDetailRegistry
{
    /** @var array<string, SourceDetail> channel => provider */
    protected array $providers = [];

    public function register(SourceDetail $provider): void
    {
        $this->providers[$provider->channel()] = $provider;
    }

    public function has(string $channel): bool
    {
        return isset($this->providers[$channel]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function detailFor(string $channel, int $inboxItemId): ?array
    {
        return isset($this->providers[$channel])
            ? $this->providers[$channel]->detailForItem($inboxItemId)
            : null;
    }
}
