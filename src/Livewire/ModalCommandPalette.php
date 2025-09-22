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

    #[On('open-modal-commands')]
    public function open(): void
    {
        $this->resetErrorBag();
        $this->result = [];
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
            redirect()->to($url)->send();
            exit;
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

        if (($plan['ok'] ?? false) && ($plan['intent'] ?? null)) {
            // Bei need confirm nach Impact fragen; MVP: sofort ausführen, wenn keine Bestätigung nötig
            if (empty($plan['confirmRequired']) || $this->forceExecute) {
                $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
                $matched = [
                    'command' => $this->findCommandByKey($plan['intent']),
                    'slots' => $plan['slots'] ?? [],
                ];
                $this->result = $gateway->executeMatched($matched, auth()->user(), $this->forceExecute);
                if (($this->result['ok'] ?? false) && !empty($this->result['navigate'])) {
                    $url = $this->result['navigate'];
                    $this->close();
                    redirect()->to($url)->send();
                    exit;
                }
            }
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


