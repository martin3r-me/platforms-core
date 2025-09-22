<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Services\CommandDispatcher;
use Platform\Core\Services\CommandGateway;
use Platform\Core\Services\IntentMatcher;
use Platform\Core\Services\LlmPlanner;

class ModalCommandPalette extends Component
{
    public bool $modalShow = false;
    public string $input = '';
    public array $result = [];
    public array $llm = [];
    public bool $forceExecute = false;
    public array $feed = [];

    #[On('open-modal-commands')]
    public function open(): void
    {
        $this->resetErrorBag();
        $this->result = [];
        $this->feed = [];
        $this->modalShow = true;
    }

    public function close(): void
    {
        $this->modalShow = false;
    }

    public function execute(): void
    {
        $text = trim($this->input);
        if ($text === '') {
            $this->addError('input', 'Bitte einen Befehl eingeben.');
            return;
        }

        $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
        $this->result = $gateway->executeText($text, auth()->user());
        if (($this->result['ok'] ?? false) && !empty($this->result['navigate'])) {
            $url = $this->result['navigate'];
            $this->close();
            $this->redirect($url, navigate: true);
            return;
        }
    }

    public function executeLlm(): void
    {
        $text = trim($this->input);
        if ($text === '') {
            $this->addError('input', 'Bitte einen Befehl eingeben.');
            return;
        }

        $planner = new LlmPlanner();
        $plan = $planner->plan($text);
        $this->llm = $plan;
        $this->feed[] = ['role' => 'user', 'text' => $text];
        $this->feed[] = ['role' => 'assistant', 'type' => 'plan', 'data' => $plan];

        if (($plan['ok'] ?? false) && ($plan['intent'] ?? null)) {
            // Bei need confirm nach Impact fragen; MVP: sofort ausführen, wenn keine Bestätigung nötig
            if (empty($plan['confirmRequired']) || $this->forceExecute) {
                $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
                $matched = [
                    'command' => $this->findCommandByKey($plan['intent']),
                    'slots' => $plan['slots'] ?? [],
                ];
                $this->result = $gateway->executeMatched($matched, auth()->user(), $this->forceExecute);
                $this->feed[] = ['role' => 'tool', 'type' => 'result', 'data' => $this->result];
                if (($this->result['ok'] ?? false) && !empty($this->result['navigate'])) {
                    $url = $this->result['navigate'];
                    $this->close();
                    $this->redirect($url, navigate: true);
                    return;
                }
            } else {
                $this->feed[] = ['role' => 'assistant', 'type' => 'confirm', 'data' => [
                    'intent' => $plan['intent'],
                    'impact' => $plan['impact'] ?? 'medium',
                    'slots' => $plan['slots'] ?? [],
                ]];
            }
        }
    }

    public function confirmAndRun(string $intent, array $slots = []): void
    {
        $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
        $matched = [
            'command' => $this->findCommandByKey($intent),
            'slots' => $slots,
        ];
        $this->result = $gateway->executeMatched($matched, auth()->user(), true);
        $this->feed[] = ['role' => 'tool', 'type' => 'result', 'data' => $this->result];
        if (($this->result['ok'] ?? false) && !empty($this->result['navigate'])) {
            $url = $this->result['navigate'];
            $this->close();
            $this->redirect($url, navigate: true);
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
        return view('platform::livewire.modal-command-palette');
    }
}


