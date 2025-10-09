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

        $this->dispatch('user-updated');
    }

    public function render()
    {
        return view('platform::livewire.modal-user');
    }
}