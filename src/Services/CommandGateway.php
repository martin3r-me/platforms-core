<?php

namespace Platform\Core\Services;

use Platform\Core\Registry\CommandRegistry;
use Platform\Core\Models\CoreCommandRun;

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
            $this->logRun($cmd, $matched['slots'], $actor, 'need_confirm', $overrideConfirm);
            return [
                'ok' => false,
                'needConfirm' => true,
                'message' => 'Bestätigung erforderlich',
                'impact' => $cmd['impact'] ?? 'medium',
            ];
        }

        $result = $this->dispatcher->dispatch($cmd, $matched['slots']);
        // Ergebnis normalisieren: Bei Erfolg keine Resolver-Flags/Listen
        if (is_array($result) && ($result['ok'] ?? false)) {
            unset($result['needResolve'], $result['needConfirm'], $result['choices'], $result['missing'], $result['confirmRequired']);
        }
        $this->logRun($cmd, $matched['slots'], $actor, ($result['ok'] ?? false) ? 'ok' : 'error', $overrideConfirm, $result['navigate'] ?? null, $result['message'] ?? null);
        return $result;
    }

    protected function logRun(array $cmd, array $slots, $actor, string $status, bool $forceExecute, ?string $navigate = null, ?string $message = null): void
    {
        try {
            CoreCommandRun::create([
                'user_id' => $actor?->id ?? null,
                'team_id' => method_exists($actor, 'currentTeam') ? ($actor->currentTeam?->id ?? null) : null,
                'command_key' => $cmd['key'] ?? 'unknown',
                'impact' => $cmd['impact'] ?? 'low',
                'force_execute' => $forceExecute,
                'slots' => $slots,
                'result_status' => $status,
                'navigate' => $navigate,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            // Logging failures sollten die Ausführung nicht bremsen
        }
    }
}


