<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Services\CommandDispatcher;
use Platform\Core\Services\CommandGateway;
use Platform\Core\Services\IntentMatcher;

class ModalCommandPalette extends Component
{
    public bool $modalShow = false;
    public string $input = '';
    public array $result = [];

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
    }

    public function render()
    {
        return view('platform::livewire.modal-command-palette');
    }
}


