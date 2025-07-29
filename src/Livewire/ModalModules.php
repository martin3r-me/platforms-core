<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Livewire\Attributes\On; 

class ModalModules extends Component
{
    public $modalShow;
    public $modules = [];

    #[On('open-modal-modules')] 
    public function openModalModules()
    {
        $this->modalShow = true;
    }

    

    public function mount()
    {
        $this->modalShow = false;

        if (auth()->check()) {
            // Wenn der User eingeloggt ist → nur Module für seinen Guard
            $this->modules = PlatformCore::getVisibleModules();
        } else {
            // Wenn kein Login → alle Module (z. B. nur für öffentliche Links)
            $this->modules = PlatformCore::getModules();
        }
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('platform::livewire.modal-modules');
    }
}