<?php

namespace Platform\Core\Support;

use Platform\Core\Contracts\TemplateProvider;

/**
 * Null-Adapter für den TemplateProvider-Port: keine Vorlagen.
 * Default-Bindung, solange kein Wissens-Modul (knowledge) einen echten Adapter bindet.
 */
class NullTemplateProvider implements TemplateProvider
{
    public function templates(int $teamId, ?string $branch = null): array
    {
        return [];
    }

    public function template(int $teamId, string $key): ?array
    {
        return null;
    }
}
