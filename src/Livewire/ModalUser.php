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

    // Token Management Properties
    public $newTokenName = '';
    public $newTokenExpiry = 'never';
    public $newTokenCreated = null;
    public $showNewToken = false;

    protected $listeners = ['open-modal-user' => 'openModal'];

    public function mount()
    {
        $this->user = auth()->user()->toArray();
    }

    public function openModal()
    {
        $this->modalShow = true;
        $this->activeTab = 'profile';
        $this->resetTokenForm();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        if ($tab !== 'tokens') {
            $this->resetTokenForm();
        }
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

    // ========================================
    // API Token Management
    // ========================================

    /**
     * Computed Property: Aktive API-Tokens des Users
     */
    public function getApiTokensProperty()
    {
        return Auth::user()->activeTokens();
    }

    /**
     * Erstellt einen neuen API-Token
     */
    public function createApiToken()
    {
        $this->validate([
            'newTokenName' => 'required|string|min:3|max:255',
            'newTokenExpiry' => 'required|in:30_days,1_year,never',
        ]);

        $expiresAt = match ($this->newTokenExpiry) {
            '30_days' => now()->addDays(30),
            '1_year' => now()->addYear(),
            'never' => null,
            default => null,
        };

        $tokenResult = Auth::user()->createToken($this->newTokenName, ['*'], $expiresAt);

        $this->newTokenCreated = $tokenResult->accessToken;
        $this->showNewToken = true;

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'API-Token erfolgreich erstellt!'
        ]);
    }

    /**
     * Widerruft einen Token
     */
    public function revokeApiToken($tokenId)
    {
        $success = Auth::user()->revokeToken($tokenId);

        if ($success) {
            $this->dispatch('notice', [
                'type' => 'success',
                'message' => 'Token erfolgreich widerrufen.'
            ]);
        } else {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Token konnte nicht widerrufen werden.'
            ]);
        }
    }

    /**
     * Schließt die Token-Anzeige und setzt das Formular zurück
     */
    public function closeNewTokenDisplay()
    {
        $this->resetTokenForm();
    }

    /**
     * Setzt das Token-Formular zurück
     */
    protected function resetTokenForm()
    {
        $this->newTokenName = '';
        $this->newTokenExpiry = 'never';
        $this->newTokenCreated = null;
        $this->showNewToken = false;
    }

    // ========================================
    // Meta Integration
    // ========================================

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
