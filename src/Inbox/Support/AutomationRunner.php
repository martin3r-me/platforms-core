<?php

namespace Platform\Core\Inbox\Support;

use Illuminate\Support\Facades\Log;

/**
 * Führt bei einem Engine-Event alle passenden Automationen aus — deterministisch,
 * agent-frei, einzeln fehler-isoliert (eine fehlerhafte Automation reißt die
 * anderen nicht mit). Wird im CoreServiceProvider auf die Engine-Events gelauscht.
 */
class AutomationRunner
{
    public function __construct(
        protected AutomationRegistry $registry,
    ) {}

    public function handle(object $event): void
    {
        foreach ($this->registry->for($event::class) as $automation) {
            try {
                if ($automation->matches($event)) {
                    $automation->run($event);
                }
            } catch (\Throwable $e) {
                Log::warning('Inbox-Automation fehlgeschlagen', [
                    'automation' => $automation::class,
                    'event'      => $event::class,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
