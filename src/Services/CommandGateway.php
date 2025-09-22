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

    public function executeMatched(array $matched, $actor = null, bool $overrideConfirm = false): array
    {
        $cmd = $matched['command'];
        // Guard/Policy (MVP: nur Guard prüfen)
        $guard = $cmd['guard'] ?? 'web';
        if (auth()->getDefaultDriver() !== $guard) {
            return ['ok' => false, 'message' => 'Nicht erlaubt für diesen Guard'];
        }

        // Confirm bei medium/high impact (UI muss Confirm schicken – MVP: abbrechen)
        $confirmRequired = $cmd['confirmRequired'] ?? false;
        if ($confirmRequired && !$overrideConfirm && !request()->boolean('confirm', false)) {
            return [
                'ok' => false,
                'needConfirm' => true,
                'message' => 'Bestätigung erforderlich',
                'impact' => $cmd['impact'] ?? 'medium',
            ];
        }

        return $this->dispatcher->dispatch($cmd, $matched['slots']);
    }
}


