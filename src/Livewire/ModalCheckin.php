<?php

namespace Platform\Core\Livewire;

use Livewire\Component;

class ModalCheckin extends Component
{
    public $modalShow = false;

    protected $listeners = ['open-modal-checkin' => 'openModal'];

    public function mount()
    {
        // Initialisierung falls nötig
    }

    public function openModal()
    {
        $this->modalShow = true;
    }

    public function render()
    {
        return view('platform::livewire.modal-checkin');
    }
}
