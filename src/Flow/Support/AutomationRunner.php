<?php

namespace Platform\Core\Flow\Support;

use Illuminate\Support\Facades\Log;

/**
 * Führt bei einem Domänen-Event alle passenden Automationen aus — deterministisch,
 * agent-frei, einzeln fehler-isoliert (eine fehlerhafte Automation reißt die
 * anderen nicht mit). Wird im ServiceProvider auf die relevanten Events gelauscht.
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
                Log::warning('Flow-Automation fehlgeschlagen', [
                    'automation' => $automation::class,
                    'event'      => $event::class,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
