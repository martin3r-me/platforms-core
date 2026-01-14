<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\CommsChannel;
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
        'is_primary' => true,
    ];

    public ?string $postmarkDomainMessage = null;

    /**
     * Channels (UI list) – stored at root team.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $channels = [];

    /**
     * New channel form (UI).
     *
     * @var array<string, mixed>
     */
    public array $newChannel = [
        'type' => 'email',
        'provider' => 'postmark',
        'sender_identifier' => '',
        'name' => '',
        'visibility' => 'private', // private|team
    ];

    public ?string $channelsMessage = null;

    #[On('open-modal-comms')]
    public function openModal(array $payload = []): void
    {
        $this->open = true;
        $this->loadPostmarkConnection();
        $this->loadChannels();
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
            ->orderBy('domain')
            ->get()
            ->map(fn (CommsProviderConnectionDomain $d) => [
                'id' => (int) $d->id,
                'domain' => (string) $d->domain,
                'is_primary' => (bool) $d->is_primary,
                'is_verified' => (bool) $d->is_verified,
                'last_error' => $d->last_error ? (string) $d->last_error : null,
            ])
            ->all();
    }

    public function canCreateTeamSharedChannel(): bool
    {
        return $this->canManageProviderConnections();
    }

    public function loadChannels(): void
    {
        $this->channelsMessage = null;
        $this->channels = [];

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;
        $this->rootTeamId = (int) $rootTeam->id;
        $this->rootTeamName = (string) ($rootTeam->name ?? '');

        // For now, we list only email/postmark channels (we'll expand later).
        $this->channels = CommsChannel::query()
            ->where('team_id', $rootTeam->id)
            ->where('type', 'email')
            ->orderByDesc('is_active')
            ->orderBy('visibility')
            ->orderBy('sender_identifier')
            ->get()
            ->map(fn (CommsChannel $c) => [
                'id' => (int) $c->id,
                'type' => (string) $c->type,
                'provider' => (string) $c->provider,
                'sender_identifier' => (string) $c->sender_identifier,
                'name' => $c->name ? (string) $c->name : null,
                'visibility' => (string) $c->visibility,
                'is_active' => (bool) $c->is_active,
            ])
            ->all();
    }

    public function createChannel(): void
    {
        $this->channelsMessage = null;

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->channelsMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $this->validate([
            'newChannel.type' => ['required', 'string', 'max:32'],
            'newChannel.provider' => ['required', 'string', 'max:64'],
            'newChannel.sender_identifier' => ['required', 'string', 'max:255'],
            'newChannel.name' => ['nullable', 'string', 'max:255'],
            'newChannel.visibility' => ['required', 'in:private,team'],
        ]);

        $type = (string) $this->newChannel['type'];
        $provider = (string) $this->newChannel['provider'];
        $sender = trim((string) $this->newChannel['sender_identifier']);
        $visibility = (string) $this->newChannel['visibility'];

        if ($visibility === 'team' && !$this->canCreateTeamSharedChannel()) {
            $this->channelsMessage = '⛔️ Teamweite Kanäle dürfen nur Owner/Admin des Root-Teams anlegen.';
            return;
        }

        // For email, basic validation + (optional) enforce configured domains
        if ($type === 'email') {
            if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
                $this->channelsMessage = '⛔️ Bitte eine gültige E‑Mail-Adresse als Absender eintragen.';
                return;
            }
        }

        $connectionId = null;
        if ($type === 'email' && $provider === 'postmark') {
            $conn = CommsProviderConnection::forTeamProvider($rootTeam, 'postmark');
            if (!$conn) {
                $this->channelsMessage = '⛔️ Keine Postmark Connection gefunden. Bitte zuerst im Tab „Connections“ speichern.';
                return;
            }
            $connectionId = $conn->id;

            // Absender-Domain MUSS in hinterlegten Domains enthalten sein.
            $domain = strtolower((string) substr(strrchr($sender, '@') ?: '', 1));
            $configuredDomains = $conn->domains()->pluck('domain')->map(fn ($d) => strtolower((string) $d))->all();
            if (empty($configuredDomains)) {
                $this->channelsMessage = '⛔️ Bitte zuerst mindestens eine Domain in „Connections“ hinterlegen (Postmark Domains).';
                return;
            }
            if (!$domain || !in_array($domain, $configuredDomains, true)) {
                $this->channelsMessage = '⛔️ Absender-Domain ist nicht in den Postmark-Domains hinterlegt.';
                return;
            }
        }

        try {
            CommsChannel::create([
                'team_id' => $rootTeam->id,
                'created_by_user_id' => $user->id,
                'comms_provider_connection_id' => $connectionId,
                'type' => $type,
                'provider' => $provider,
                'name' => trim((string) ($this->newChannel['name'] ?? '')) ?: null,
                'sender_identifier' => $sender,
                'visibility' => $visibility,
                'is_active' => true,
                'meta' => [],
            ]);
        } catch (QueryException $e) {
            $this->channelsMessage = '⛔️ Dieser Kanal existiert bereits (Team/Typ/Absender).';
            return;
        }

        $this->newChannel['sender_identifier'] = '';
        $this->newChannel['name'] = '';
        $this->newChannel['visibility'] = 'private';

        $this->loadChannels();
        $this->channelsMessage = '✅ Kanal angelegt.';
    }

    public function removeChannel(int $channelId): void
    {
        $this->channelsMessage = null;

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->channelsMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $channel = CommsChannel::query()
            ->where('team_id', $rootTeam->id)
            ->whereKey($channelId)
            ->first();

        if (!$channel) {
            $this->channelsMessage = '⛔️ Kanal nicht gefunden.';
            return;
        }

        // Owner/Admin can delete anything; otherwise only private channels created by the user
        if (!$this->canManageProviderConnections()) {
            if ($channel->visibility !== 'private' || (int) $channel->created_by_user_id !== (int) $user->id) {
                $this->channelsMessage = '⛔️ Keine Berechtigung zum Löschen dieses Kanals.';
                return;
            }
        }

        $channel->delete();
        $this->loadChannels();
        $this->channelsMessage = '✅ Kanal entfernt.';
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
            'postmarkNewDomain.is_primary' => ['boolean'],
        ]);

        $domain = strtolower(trim((string) $this->postmarkNewDomain['domain']));
        $purpose = 'email';
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

