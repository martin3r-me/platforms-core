<?php

namespace Platform\Core\Services;

use Platform\Core\Registry\CommandRegistry;

class CommandGateway
{
    public function __construct(
        protected IntentMatcher $matcher,
        protected CommandDispatcher $dispatcher,
    ) {}

    public function executeText(string $text, $actor = null): array
    {
        $matched = $this->matcher->match($text);
        if (!$matched) {
            return ['ok' => false, 'message' => 'Kein Befehl erkannt'];
        }
        return $this->executeMatched($matched, $actor);
    }

    public function executeMatched(array $matched, $actor = null): array
    {
        // TODO: Guard/Policy anhand $matched['command']['guard']
        return $this->dispatcher->dispatch($matched['command'], $matched['slots']);
    }
}


