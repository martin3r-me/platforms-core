<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Integrations\Models\IntegrationsMetaToken;
use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Integrations\Models\IntegrationsInstagramAccount;

class ModalUser extends Component
{
    public $modalShow = false;
    public $user = [];
    public $activeTab = 'profile';

    protected $listeners = ['open-modal-user' => 'openModal'];

    public function mount()
    {
        $this->user = auth()->user()->toArray();
    }

    public function openModal()
    {
        $this->modalShow = true;
        $this->activeTab = 'profile';
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
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

    public function getMetaTokenProperty()
    {
        $user = Auth::user();
        return IntegrationsMetaToken::where('user_id', $user->id)
            ->first();
    }

    public function getFacebookPagesProperty()
    {
        $user = Auth::user();
        return IntegrationsFacebookPage::where('user_id', $user->id)
            ->get();
    }

    public function getInstagramAccountsProperty()
    {
        $user = Auth::user();
        return IntegrationsInstagramAccount::where('user_id', $user->id)
            ->get();
    }

    public function deleteMetaToken()
    {
        $user = Auth::user();
        $metaToken = IntegrationsMetaToken::where('user_id', $user->id)
            ->first();

        if ($metaToken) {
            $metaToken->delete();

            $this->dispatch('notice', [
                'type' => 'success',
                'message' => 'Meta-Verbindung wurde erfolgreich gelöscht.'
            ]);
        }
    }

    public function render()
    {
        return view('platform::livewire.modal-user');
    }
}