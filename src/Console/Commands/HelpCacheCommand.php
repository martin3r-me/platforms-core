<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Services\HelpDiscovery;

class HelpCacheCommand extends Command
{
    protected $signature = 'help:clear
        {--warmup : Nach dem Löschen alle Seiten vorab cachen}';

    protected $description = 'Löscht den Help-Dokumentations-Cache. Mit --warmup werden alle Seiten vorab gecacht.';

    public function handle(): int
    {
        HelpDiscovery::clearCache();
        $this->info('Help-Cache gelöscht.');

        if ($this->option('warmup')) {
            $this->info('Wärme Cache vor...');

            $tree = HelpDiscovery::getTree();
            $count = 0;

            foreach ($tree as $module) {
                if ($module['has_index']) {
                    HelpDiscovery::getPage($module['key'], 'index');
                    $count++;
                }

                foreach ($module['sections'] as $section) {
                    if ($section['type'] === 'page') {
                        HelpDiscovery::getPage($module['key'], $section['path']);
                        $count++;
                    } elseif ($section['type'] === 'group') {
                        foreach ($section['pages'] as $page) {
                            HelpDiscovery::getPage($module['key'], $page['path']);
                            $count++;
                        }
                    }
                }
            }

            $this->info("{$count} Seiten gecacht.");
        }

        return self::SUCCESS;
    }
}
