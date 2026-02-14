<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

    // MCP Client Management Properties
    public $newMcpClientName = '';
    public $newMcpClientRedirect = 'http://127.0.0.1';
    public $newMcpClientPublic = false;
    public $newMcpClientCreated = null;
    public $newMcpClientSecret = null;
    public $showNewMcpClient = false;

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
        $this->resetMcpClientForm();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        if ($tab !== 'tokens') {
            $this->resetTokenForm();
        }
        if ($tab !== 'mcp') {
            $this->resetMcpClientForm();
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

        try {
            $tokenResult = Auth::user()->createToken($this->newTokenName, ['*'], $expiresAt);
        } catch (\LogicException $e) {
            // Passport Keys nicht konfiguriert
            Log::error('Passport key error beim Token erstellen', [
                'userId' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'API-Tokens sind derzeit nicht verfügbar. Bitte kontaktiere den Administrator.',
            ]);
            return;
        }

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
    // MCP Client Management
    // ========================================

    /**
     * Computed Property: MCP Clients des Users
     */
    public function getMcpClientsProperty()
    {
        return \Platform\Core\Models\PassportClient::query()
            ->where('owner_id', Auth::id())
            ->where('owner_type', get_class(Auth::user()))
            ->where('revoked', false)
            ->whereJsonContains('grant_types', 'authorization_code')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Erstellt einen neuen MCP Client
     */
    public function createMcpClient()
    {
        $this->validate([
            'newMcpClientName' => 'required|string|min:3|max:255',
            'newMcpClientRedirect' => 'required|url',
        ]);

        try {
            $clientRepository = app(\Laravel\Passport\ClientRepository::class);

            $client = $clientRepository->createAuthorizationCodeGrantClient(
                name: $this->newMcpClientName,
                redirectUris: [$this->newMcpClientRedirect],
                confidential: !$this->newMcpClientPublic,
                user: Auth::user(),
            );

            $this->newMcpClientCreated = $client->id;
            $this->newMcpClientSecret = $client->plainSecret;
            $this->showNewMcpClient = true;

            $this->dispatch('notice', [
                'type' => 'success',
                'message' => 'MCP Client erfolgreich erstellt!'
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler beim Erstellen des MCP Clients', [
                'userId' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'MCP Client konnte nicht erstellt werden.',
            ]);
        }
    }

    /**
     * Widerruft einen MCP Client
     */
    public function revokeMcpClient($clientId)
    {
        $client = \Platform\Core\Models\PassportClient::query()
            ->where('id', $clientId)
            ->where('owner_id', Auth::id())
            ->where('owner_type', get_class(Auth::user()))
            ->first();

        if ($client) {
            $client->revoked = true;
            $client->save();

            // Tokens des Clients widerrufen
            $client->tokens()->update(['revoked' => true]);

            $this->dispatch('notice', [
                'type' => 'success',
                'message' => 'MCP Client erfolgreich widerrufen.'
            ]);
        } else {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'MCP Client konnte nicht gefunden werden.'
            ]);
        }
    }

    /**
     * Schließt die MCP Client-Anzeige
     */
    public function closeNewMcpClientDisplay()
    {
        $this->resetMcpClientForm();
    }

    /**
     * Setzt das MCP Client-Formular zurück
     */
    protected function resetMcpClientForm()
    {
        $this->newMcpClientName = '';
        $this->newMcpClientRedirect = 'http://127.0.0.1';
        $this->newMcpClientPublic = false;
        $this->newMcpClientCreated = null;
        $this->newMcpClientSecret = null;
        $this->showNewMcpClient = false;
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
