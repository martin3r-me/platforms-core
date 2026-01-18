<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Services\MetaIntegrationService;

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

    public function getMetaConnectionProperty()
    {
        $user = Auth::user();
        $metaService = app(MetaIntegrationService::class);
        return $metaService->getConnectionForUser($user);
    }
    
    /**
     * @deprecated Verwende stattdessen getMetaConnectionProperty()
     */
    public function getMetaTokenProperty()
    {
        return $this->getMetaConnectionProperty();
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

    public function deleteMetaConnection()
    {
        $user = Auth::user();
        $metaService = app(MetaIntegrationService::class);
        $metaConnection = $metaService->getConnectionForUser($user);

        if ($metaConnection) {
            $metaConnection->delete();

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