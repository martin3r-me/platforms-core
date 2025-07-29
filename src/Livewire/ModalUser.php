<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Livewire\Attributes\On; 

class ModalUser extends Component
{
    public $modalShow;

    #[On('open-modal-user')] 
    public function openModalUser()
    {
        $this->modalShow = true;
    }

    

    public function mount()
    {
        $this->modalShow = false;
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('platform::livewire.modal-user');
    }
}