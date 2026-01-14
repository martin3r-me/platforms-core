<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\CommsProviderConnection;
use Platform\Core\Models\CommsProviderConnectionDomain;
use Platform\Core\Models\Team;

/**
 * UI-only Comms v2 shell (no data, no logic).
 * Triggered from the navbar via the `open-modal-comms` event.
 */
class ModalComms extends Component
{
    public bool $open = false;

    /**
     * Postmark provider connection form (stored at root team level).
     * Secrets remain encrypted in DB via model casts.
     *
     * @var array<string, mixed>
     */
    public array $postmark = [
        'server_token' => '',
        'message_stream' => 'outbound',
        'inbound_user' => '',
        'inbound_pass' => '',
        'signing_secret' => '',
    ];

    public bool $postmarkConfigured = false;
    public ?string $postmarkMessage = null;
    public ?int $rootTeamId = null;
    public ?string $rootTeamName = null;

    /**
     * Loaded Postmark domains for the active connection (UI list).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $postmarkDomains = [];

    /**
     * New domain form (UI).
     *
     * @var array<string, mixed>
     */
    public array $postmarkNewDomain = [
        'domain' => '',
        'purpose' => 'sending',
        'is_primary' => true,
    ];

    public ?string $postmarkDomainMessage = null;

    #[On('open-modal-comms')]
    public function openModal(array $payload = []): void
    {
        $this->open = true;
        $this->loadPostmarkConnection();
    }

    public function closeModal(): void
    {
        $this->open = false;
    }

    public function canManageProviderConnections(): bool
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            return false;
        }

        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        return $rootTeam->users()
            ->where('user_id', $user->id)
            ->wherePivotIn('role', [TeamRole::OWNER->value, TeamRole::ADMIN->value])
            ->exists();
    }

    public function loadPostmarkConnection(): void
    {
        $this->postmarkMessage = null;
        $this->postmarkDomainMessage = null;
        $this->postmarkDomains = [];

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$team) {
            $this->postmarkConfigured = false;
            $this->rootTeamId = null;
            $this->rootTeamName = null;
            return;
        }

        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;
        $this->rootTeamId = (int) $rootTeam->id;
        $this->rootTeamName = (string) ($rootTeam->name ?? '');

        $conn = CommsProviderConnection::forTeamProvider($rootTeam, 'postmark');
        if (!$conn) {
            $this->postmarkConfigured = false;
            return;
        }

        $this->postmarkConfigured = true;

        // Do not prefill secrets. Only show non-sensitive defaults if present.
        $creds = is_array($conn->credentials) ? $conn->credentials : [];
        if (!empty($creds['message_stream'])) {
            $this->postmark['message_stream'] = (string) $creds['message_stream'];
        }
        if (!empty($creds['inbound_user'])) {
            $this->postmark['inbound_user'] = (string) $creds['inbound_user'];
        }
        // Keep server_token / inbound_pass / signing_secret empty on purpose.

        $this->loadPostmarkDomains($conn);
    }

    private function loadPostmarkDomains(CommsProviderConnection $conn): void
    {
        $this->postmarkDomains = $conn->domains()
            ->orderByDesc('is_primary')
            ->orderBy('purpose')
            ->orderBy('domain')
            ->get()
            ->map(fn (CommsProviderConnectionDomain $d) => [
                'id' => (int) $d->id,
                'domain' => (string) $d->domain,
                'purpose' => (string) $d->purpose,
                'is_primary' => (bool) $d->is_primary,
                'is_verified' => (bool) $d->is_verified,
                'last_error' => $d->last_error ? (string) $d->last_error : null,
            ])
            ->all();
    }

    public function savePostmarkConnection(): void
    {
        $this->postmarkMessage = null;
        $this->postmarkDomainMessage = null;

        if (!$this->canManageProviderConnections()) {
            $this->postmarkMessage = '⛔️ Keine Berechtigung: nur Owner/Admin des Root-Teams kann Provider-Connections verwalten.';
            return;
        }

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->postmarkMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $this->validate([
            'postmark.server_token' => ['required', 'string', 'min:10'],
            'postmark.message_stream' => ['required', 'string', 'max:120'],
            'postmark.inbound_user' => ['nullable', 'string', 'max:255'],
            'postmark.inbound_pass' => ['nullable', 'string', 'max:255'],
            'postmark.signing_secret' => ['nullable', 'string', 'max:255'],
        ]);

        CommsProviderConnection::updateOrCreate(
            [
                'team_id' => $rootTeam->id,
                'provider' => 'postmark',
            ],
            [
                'created_by_user_id' => $user->id,
                'name' => 'Postmark',
                'is_active' => true,
                'credentials' => [
                    'server_token' => (string) $this->postmark['server_token'],
                    'message_stream' => (string) $this->postmark['message_stream'],
                    'inbound_user' => (string) ($this->postmark['inbound_user'] ?? ''),
                    'inbound_pass' => (string) ($this->postmark['inbound_pass'] ?? ''),
                    'signing_secret' => (string) ($this->postmark['signing_secret'] ?? ''),
                ],
                'meta' => [],
                'last_error' => null,
            ]
        );

        $this->postmarkConfigured = true;
        $this->postmarkMessage = '✅ Postmark Connection gespeichert (am Root-Team).';

        // Reload domains list (connection might have been created just now)
        if ($conn = CommsProviderConnection::forTeamProvider($rootTeam, 'postmark')) {
            $this->loadPostmarkDomains($conn);
        }

        // Clear secrets from the form (avoid showing them back).
        $this->postmark['server_token'] = '';
        $this->postmark['inbound_pass'] = '';
        $this->postmark['signing_secret'] = '';
    }

    public function addPostmarkDomain(): void
    {
        $this->postmarkDomainMessage = null;

        if (!$this->canManageProviderConnections()) {
            $this->postmarkDomainMessage = '⛔️ Keine Berechtigung: nur Owner/Admin des Root-Teams.';
            return;
        }

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->postmarkDomainMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $conn = CommsProviderConnection::forTeamProvider($rootTeam, 'postmark');
        if (!$conn) {
            $this->postmarkDomainMessage = '⛔️ Bitte zuerst Postmark speichern (Connection existiert noch nicht).';
            return;
        }

        $this->validate([
            'postmarkNewDomain.domain' => [
                'required',
                'string',
                'max:255',
                // simple domain validation (subdomains allowed)
                'regex:/^(?!-)(?:[a-z0-9-]{1,63}\\.)+[a-z]{2,63}$/i',
            ],
            'postmarkNewDomain.purpose' => ['required', 'string', 'max:64'],
            'postmarkNewDomain.is_primary' => ['boolean'],
        ]);

        $domain = strtolower(trim((string) $this->postmarkNewDomain['domain']));
        $purpose = trim((string) $this->postmarkNewDomain['purpose']);
        $isPrimary = (bool) ($this->postmarkNewDomain['is_primary'] ?? false);

        try {
            $created = $conn->domains()->create([
                'domain' => $domain,
                'purpose' => $purpose,
                'is_primary' => $isPrimary,
                'is_verified' => false,
                'meta' => [],
            ]);

            if ($isPrimary) {
                $conn->domains()
                    ->where('purpose', $purpose)
                    ->where('id', '!=', $created->id)
                    ->update(['is_primary' => false]);
            }
        } catch (QueryException $e) {
            $this->postmarkDomainMessage = '⛔️ Domain existiert bereits für diesen Purpose.';
            return;
        }

        $this->postmarkNewDomain['domain'] = '';
        $this->postmarkNewDomain['purpose'] = $purpose;
        $this->postmarkNewDomain['is_primary'] = true;

        $this->loadPostmarkDomains($conn);
        $this->postmarkDomainMessage = '✅ Domain hinzugefügt.';
    }

    public function setPostmarkPrimaryDomain(int $domainId): void
    {
        $this->postmarkDomainMessage = null;

        if (!$this->canManageProviderConnections()) {
            $this->postmarkDomainMessage = '⛔️ Keine Berechtigung: nur Owner/Admin des Root-Teams.';
            return;
        }

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->postmarkDomainMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $conn = CommsProviderConnection::forTeamProvider($rootTeam, 'postmark');
        if (!$conn) {
            $this->postmarkDomainMessage = '⛔️ Postmark Connection nicht gefunden.';
            return;
        }

        $domain = $conn->domains()->whereKey($domainId)->first();
        if (!$domain) {
            $this->postmarkDomainMessage = '⛔️ Domain nicht gefunden.';
            return;
        }

        $conn->domains()
            ->where('purpose', $domain->purpose)
            ->update(['is_primary' => false]);

        $domain->is_primary = true;
        $domain->save();

        $this->loadPostmarkDomains($conn);
        $this->postmarkDomainMessage = '✅ Primary gesetzt.';
    }

    public function removePostmarkDomain(int $domainId): void
    {
        $this->postmarkDomainMessage = null;

        if (!$this->canManageProviderConnections()) {
            $this->postmarkDomainMessage = '⛔️ Keine Berechtigung: nur Owner/Admin des Root-Teams.';
            return;
        }

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->postmarkDomainMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $conn = CommsProviderConnection::forTeamProvider($rootTeam, 'postmark');
        if (!$conn) {
            $this->postmarkDomainMessage = '⛔️ Postmark Connection nicht gefunden.';
            return;
        }

        $deleted = $conn->domains()->whereKey($domainId)->delete();
        if (!$deleted) {
            $this->postmarkDomainMessage = '⛔️ Domain nicht gefunden.';
            return;
        }

        $this->loadPostmarkDomains($conn);
        $this->postmarkDomainMessage = '✅ Domain entfernt.';
    }

    public function render()
    {
        return view('platform::livewire.modal-comms');
    }
}

