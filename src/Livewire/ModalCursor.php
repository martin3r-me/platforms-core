<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Services\CommandDispatcher;
use Platform\Core\Services\CommandGateway;
use Platform\Core\Services\IntentMatcher;
use Platform\Core\Services\LlmPlanner;

class ModalCursor extends Component
{
    public bool $modalShow = false;
    public string $input = '';
    public bool $forceExecute = false;
    public array $feed = [];
    public string $lastUserText = '';

    #[On('open-modal-cursor')]
    public function open(): void
    {
        $this->resetErrorBag();
        $this->feed = [];
        $this->modalShow = true;
    }

    public function close(): void
    {
        $this->modalShow = false;
    }

    public function planAndMaybeRun(): void
    {
        $text = trim($this->input);
        if ($text === '') {
            $this->addError('input', 'Bitte einen Befehl eingeben.');
            return;
        }

        $this->feed[] = ['role' => 'user', 'text' => $text];
        $this->lastUserText = $text;

        $planner = new LlmPlanner();
        $plan = $planner->plan($text);
        $this->feed[] = ['role' => 'assistant', 'type' => 'plan', 'data' => $plan];

        if (($plan['ok'] ?? false) && ($plan['intent'] ?? null)) {
            $conf = (float)($plan['confidence'] ?? 0.0);
            $autoThreshold = 0.8;
            if ($this->forceExecute || (empty($plan['confirmRequired']) && $conf >= $autoThreshold)) {
                $this->executeIntent($plan['intent'], $plan['slots'] ?? []);
                $this->assistantFollowUp($text);
            } else {
                $this->feed[] = ['role' => 'assistant', 'type' => 'confirm', 'data' => [
                    'intent' => $plan['intent'],
                    'impact' => $plan['impact'] ?? 'medium',
                    'slots' => $plan['slots'] ?? [],
                    'confidence' => $conf,
                ]];
                // Auch ohne Auto-Run kann das Modell schon eine kurze Antwort geben
                $this->assistantFollowUp($text);
            }
        } else {
            // Kein Tool-Vorschlag: klassisch antworten
            $this->assistantFollowUp($text);
        }

        $this->input = '';
    }

    public function confirmAndRun(string $intent, array $slots = []): void
    {
        $this->executeIntent($intent, $slots, true);
        if ($this->lastUserText !== '') {
            $this->assistantFollowUp($this->lastUserText);
        }
    }

    protected function executeIntent(string $intent, array $slots = [], bool $override = false): void
    {
        $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
        $matched = [
            'command' => $this->findCommandByKey($intent),
            'slots' => $slots,
        ];
        $result = $gateway->executeMatched($matched, auth()->user(), $override || $this->forceExecute);
        $this->feed[] = ['role' => 'tool', 'type' => 'result', 'data' => $result];
        if (($result['ok'] ?? false) && !empty($result['navigate'])) {
            $url = $result['navigate'];
            $this->close();
            $this->redirect($url, navigate: true);
        }
    }

    protected function assistantFollowUp(string $userText): void
    {
        // Kontext aus den letzten Result-Bubbles sammeln (z. B. tasks)
        $context = [];
        foreach (array_reverse($this->feed, true) as $b) {
            if (($b['type'] ?? '') === 'result' && !empty($b['data']['data'])) {
                $context[] = $b['data']['data'];
            }
            if (count($context) >= 3) break;
        }
        $planner = new LlmPlanner();
        $resp = $planner->assistantRespond($userText, $context);
        if (($resp['ok'] ?? false) && !empty($resp['text'])) {
            $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => $resp['text']]];
        }
    }

    protected function findCommandByKey(string $key): array
    {
        foreach (\Platform\Core\Registry\CommandRegistry::all() as $module => $cmds) {
            foreach ($cmds as $c) {
                if (($c['key'] ?? null) === $key) {
                    return $c;
                }
            }
        }
        return [];
    }

    public function render()
    {
        return view('platform::livewire.modal-cursor');
    }
}


