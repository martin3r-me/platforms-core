<?php

namespace Platform\Core\Inbox\Support;

use Platform\Core\Inbox\Contracts\SourceList;

/**
 * Sammelstelle für SourceList-Provider je Kanal. Quell-Module registrieren hier
 * beim Boot ihren Provider; StreamQuery::listStreamForUser fragt alle ab und fügt
 * sie zu EINEM Stream zusammen.
 *
 * Als Singleton in Core gebunden. Leere Registry = nur der Core-native Kanal
 * (recording) erscheint (defensiv, nichts bricht).
 */
class SourceListRegistry
{
    /** @var array<string, SourceList> channel => provider */
    protected array $providers = [];

    public function register(SourceList $provider): void
    {
        $this->providers[$provider->channel()] = $provider;
    }

    /** @return array<string, SourceList> */
    public function all(): array
    {
        return $this->providers;
    }
}
