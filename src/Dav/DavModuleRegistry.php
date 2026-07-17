<?php

namespace Platform\Core\Dav;

use Platform\Core\Contracts\DavModuleInterface;

/**
 * Registry, in der Module ihre {@see DavModuleInterface}-Implementierungen anmelden.
 *
 * Als Singleton im CoreServiceProvider gebunden; Module rufen in ihrem
 * ServiceProvider `app(DavModuleRegistry::class)->register(...)` auf.
 *
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
class DavModuleRegistry
{
    /** @var array<int, DavModuleInterface> */
    private array $modules = [];

    public function register(DavModuleInterface $module): void
    {
        $this->modules[] = $module;
    }

    /**
     * @return array<int, DavModuleInterface>
     */
    public function all(): array
    {
        return $this->modules;
    }
}
