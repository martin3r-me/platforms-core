<?php

namespace Platform\Core\Support;

use Platform\Core\Contracts\SuggestionProvider;

/**
 * Null-Adapter für den SuggestionProvider-Port: liefert nichts, bricht nichts.
 * Default-Bindung, solange kein Wissens-Modul (knowledge) einen echten Adapter bindet.
 */
class NullSuggestionProvider implements SuggestionProvider
{
    public function suggest(int $teamId, string $context, array $opts = []): array
    {
        return [];
    }
}
