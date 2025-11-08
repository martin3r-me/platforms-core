<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class ModalUser extends Component
{
    public $modalShow = false;
    public $user = [];

    protected $listeners = ['open-modal-user' => 'openModal'];

    public function mount()
    {
        $this->user = auth()->user()->toArray();
    }

    public function openModal()
    {
        $this->modalShow = true;
        
        // Organization-Kontext setzen - nur Kontext-Management, keine Zeiterfassung
        $this->dispatch('organization', [
            'context_type' => \Platform\Core\Models\User::class,
            'context_id' => auth()->id(),
            'allow_time_entry' => false,
            'allow_context_management' => true,
            'can_link_to_entity' => true,
        ]);
    }

    public function save()
    {
        $this->validate([
            'user.fullname' => 'nullable|string|max:255',
            'user.email' => 'required|email|unique:users,email,' . auth()->id(),
        ]);

        $user = Auth::user();
        $user->update([
            'fullname' => $this->user['fullname'] ?? null,
            'email' => $this->user['email'],
        ]);

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'Benutzerdaten erfolgreich gespeichert!'
        ]);
    }

    public function render()
    {
        return view('platform::livewire.modal-user');
    }
}