<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Models\CommsEmailInboundMail;
use Platform\Core\Models\CommsEmailOutboundMail;
use Platform\Core\Models\CommsProviderConnection;
use Platform\Core\Models\CommsProviderConnectionDomain;
use Platform\Core\Models\Team;
use Platform\Core\Services\Comms\PostmarkEmailService;

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
        'sender_local_part' => '',
        'sender_domain' => '',
        'name' => '',
        'visibility' => 'private', // private|team
    ];

    public ?string $channelsMessage = null;

    // --- Runtime Email (Kanäle Tab) ---
    /** @var array<int, array<string, mixed>> */
    public array $emailChannels = [];
    public ?int $activeEmailChannelId = null;
    public ?string $activeEmailChannelAddress = null;

    /** @var array<int, array<string, mixed>> */
    public array $emailThreads = [];
    public ?int $activeEmailThreadId = null;

    /** @var array<int, array<string, mixed>> */
    public array $emailTimeline = [];

    /** @var array<string, mixed> */
    public array $emailCompose = [
        'to' => '',
        'subject' => '',
        'body' => '',
    ];

    public ?string $emailMessage = null;
    /** @var array<int, array{at:string,msg:string}> */
    public array $emailDebug = [];

    private function emailDebug(string $msg): void
    {
        $this->emailDebug[] = [
            'at' => now()->format('H:i:s'),
            'msg' => $msg,
        ];
        // Keep it short
        if (count($this->emailDebug) > 12) {
            $this->emailDebug = array_slice($this->emailDebug, -12);
        }
    }

    #[On('open-modal-comms')]
    public function openModal(array $payload = []): void
    {
        $this->open = true;
        $this->loadPostmarkConnection();
        $this->loadChannels();
        $this->loadEmailRuntime();
    }

    public function closeModal(): void
    {
        $this->open = false;
    }

    public function loadEmailRuntime(): void
    {
        $this->emailMessage = null;
        $this->emailChannels = [];
        $this->emailThreads = [];
        $this->emailTimeline = [];

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        // Channels visible to the current user:
        // - team channels (shared)
        // - private channels created by user
        $channels = CommsChannel::query()
            ->where('team_id', $rootTeam->id)
            ->where('type', 'email')
            ->where('provider', 'postmark')
            ->where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->where('visibility', 'team')
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'private')->where('created_by_user_id', $user->id);
                    });
            })
            ->orderBy('visibility')
            ->orderBy('sender_identifier')
            ->get();

        $this->emailChannels = $channels->map(fn (CommsChannel $c) => [
            'id' => (int) $c->id,
            'label' => (string) $c->sender_identifier,
        ])->all();

        if (!$this->activeEmailChannelId && $channels->isNotEmpty()) {
            $this->activeEmailChannelId = (int) $channels->first()->id;
        }

        $this->refreshActiveEmailChannelLabel();
        $this->loadEmailThreads();
    }

    public function updatedActiveEmailChannelId(): void
    {
        $this->refreshActiveEmailChannelLabel();
        $this->activeEmailThreadId = null;
        $this->emailCompose['subject'] = '';
        $this->emailCompose['body'] = '';
        $this->emailCompose['to'] = '';
        $this->loadEmailThreads();
        $this->dispatch('comms:scroll-bottom');
    }

    private function refreshActiveEmailChannelLabel(): void
    {
        $this->activeEmailChannelAddress = null;
        if (!$this->activeEmailChannelId) {
            return;
        }
        foreach ($this->emailChannels as $c) {
            if ((int) ($c['id'] ?? 0) === (int) $this->activeEmailChannelId) {
                $this->activeEmailChannelAddress = (string) ($c['label'] ?? null);
                return;
            }
        }
    }

    public function loadEmailThreads(): void
    {
        $this->emailThreads = [];
        $this->emailTimeline = [];

        if (!$this->activeEmailChannelId) {
            return;
        }

        $threads = CommsEmailThread::query()
            ->where('comms_channel_id', $this->activeEmailChannelId)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $this->emailThreads = $threads->map(fn (CommsEmailThread $t) => [
            'id' => (int) $t->id,
            'subject' => (string) ($t->subject ?: 'Ohne Betreff'),
            'token' => (string) $t->token,
            'updated_at' => $t->updated_at?->toDateTimeString(),
        ])->all();

        if (!$this->activeEmailThreadId && $threads->isNotEmpty()) {
            $this->setActiveEmailThread((int) $threads->first()->id);
        }
    }

    public function setActiveEmailThread(int $threadId): void
    {
        $this->activeEmailThreadId = $threadId;
        $this->loadEmailTimeline();

        // Pre-fill "to" for reply from thread rollup (fallback: last inbound mail).
        $thread = CommsEmailThread::query()->whereKey($threadId)->first();
        if ($thread?->last_inbound_from_address) {
            $this->emailCompose['to'] = (string) $thread->last_inbound_from_address;
        } else {
            $lastInbound = CommsEmailInboundMail::query()
                ->where('thread_id', $threadId)
                ->orderByDesc('received_at')
                ->first();
            if ($lastInbound?->from) {
                $this->emailCompose['to'] = $this->extractEmailAddress((string) $lastInbound->from) ?: (string) $lastInbound->from;
            }
        }
        $this->dispatch('comms:scroll-bottom');
    }

    public function deleteEmailThread(int $threadId): void
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->emailMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $thread = CommsEmailThread::query()
            ->where('team_id', $rootTeam->id)
            ->whereKey($threadId)
            ->first();

        if (!$thread) {
            $this->emailMessage = '⛔️ Thread nicht gefunden.';
            return;
        }

        $channel = CommsChannel::query()
            ->where('team_id', $rootTeam->id)
            ->whereKey($thread->comms_channel_id)
            ->first();

        if (!$channel) {
            $this->emailMessage = '⛔️ Kanal zum Thread nicht gefunden.';
            return;
        }

        // Permission:
        // - team channels: only owner/admin
        // - private channels: owner/admin or channel creator
        if ($channel->visibility === 'team') {
            if (!$this->canManageProviderConnections()) {
                $this->emailMessage = '⛔️ Keine Berechtigung (teamweite Kanäle nur Owner/Admin).';
                return;
            }
        } else {
            if (!$this->canManageProviderConnections() && (int) $channel->created_by_user_id !== (int) $user->id) {
                $this->emailMessage = '⛔️ Keine Berechtigung (privater Kanal gehört einem anderen User).';
                return;
            }
        }

        // Hard-delete to really keep DB clean (FK cascades delete mails/attachments).
        $thread->forceDelete();

        if ((int) $this->activeEmailThreadId === (int) $threadId) {
            $this->activeEmailThreadId = null;
            $this->emailTimeline = [];
        }

        $this->emailMessage = '✅ Thread gelöscht.';
        $this->loadEmailThreads();
        $this->dispatch('comms:scroll-bottom');
    }

    private function extractEmailAddress(string $raw): ?string
    {
        if (preg_match('/<([^>]+)>/', $raw, $m)) {
            return trim((string) ($m[1] ?? '')) ?: null;
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return $raw;
        }
        return null;
    }

    public function startNewEmailThread(): void
    {
        $this->activeEmailThreadId = null;
        $this->emailTimeline = [];
        $this->emailCompose['subject'] = '';
        $this->emailCompose['body'] = '';
        // keep "to" as-is
        $this->dispatch('comms:scroll-bottom');
    }

    public function sendEmail(): void
    {
        $this->emailMessage = null;
        $this->emailDebug = [];
        $this->emailDebug('Senden gestartet…');

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->emailMessage = '⛔️ Kein Team-Kontext gefunden.';
            $this->emailDebug('Fehler: kein Team-Kontext.');
            return;
        }

        if (!$this->activeEmailChannelId) {
            $this->emailMessage = '⛔️ Kein E‑Mail Kanal ausgewählt.';
            $this->emailDebug('Fehler: kein Kanal ausgewählt.');
            return;
        }

        $this->emailDebug('Validiere Eingaben…');
        try {
            $isReply = (bool) $this->activeEmailThreadId;
            $this->validate([
                'emailCompose.to' => [$isReply ? 'nullable' : 'required', 'email', 'max:255'],
                'emailCompose.body' => ['required', 'string', 'min:1'],
                'emailCompose.subject' => [$isReply ? 'nullable' : 'required', 'string', 'max:255'],
            ]);
        } catch (ValidationException $e) {
            $this->emailMessage = '⛔️ Bitte Eingaben prüfen.';
            $errors = $e->validator->errors()->all();
            $this->emailDebug('Validation fehlgeschlagen: ' . implode(' | ', array_slice($errors, 0, 3)));
            return;
        }

        $this->emailDebug('Lade Kanal…');
        $channel = CommsChannel::query()
            ->whereKey($this->activeEmailChannelId)
            ->where('type', 'email')
            ->where('provider', 'postmark')
            ->where('is_active', true)
            ->first();
        if (!$channel) {
            $this->emailMessage = '⛔️ E‑Mail Kanal nicht gefunden.';
            $this->emailDebug('Fehler: Kanal nicht gefunden.');
            return;
        }
        $this->emailDebug("Kanal OK (id={$channel->id}, from={$channel->sender_identifier}).");

        $subject = (string) ($this->emailCompose['subject'] ?? '');
        $isReply = false;
        $token = null;
        $to = (string) ($this->emailCompose['to'] ?? '');

        if ($this->activeEmailThreadId) {
            $thread = CommsEmailThread::query()->whereKey($this->activeEmailThreadId)->first();
            if ($thread) {
                $subject = (string) ($thread->subject ?: $subject);
                $token = (string) $thread->token;
                $isReply = true;
            }

            // For replies we normally don't ask for "to". Resolve best-effort.
            if (trim($to) === '') {
                if ($thread?->last_inbound_from_address) {
                    $to = (string) $thread->last_inbound_from_address;
                } else {
                    $lastInbound = CommsEmailInboundMail::query()
                        ->where('thread_id', $this->activeEmailThreadId)
                        ->orderByDesc('received_at')
                        ->first();
                    if ($lastInbound?->from) {
                        $to = $this->extractEmailAddress((string) $lastInbound->from) ?: (string) $lastInbound->from;
                    }
                }
                $this->emailCompose['to'] = $to;
            }
            if (trim($to) === '') {
                $this->emailMessage = '⛔️ Kein Empfänger für Antwort gefunden. Bitte neuen Thread starten und „An“ setzen.';
                $this->emailDebug('Fehler: Reply ohne Empfänger (kein last inbound).');
                return;
            }
        }

        try {
            $this->emailDebug('Sende via Postmark…');
            /** @var PostmarkEmailService $svc */
            $svc = app(PostmarkEmailService::class);
            $token = $svc->send(
                $channel,
                $to,
                $subject ?: '(Ohne Betreff)',
                nl2br(e((string) $this->emailCompose['body'])),
                null,
                [],
                [
                    'sender' => $user,
                    'token' => $token,
                    'is_reply' => $isReply,
                ]
            );
            $this->emailDebug('Postmark: OK.');
        } catch (\Throwable $e) {
            $this->emailMessage = '⛔️ Versand fehlgeschlagen: ' . $e->getMessage();
            $this->emailDebug('Fehler: ' . $e->getMessage());
            return;
        }

        $this->emailCompose['body'] = '';
        if (!$this->activeEmailThreadId) {
            $this->emailCompose['subject'] = '';
        }

        // Refresh threads & select the thread for the returned token
        $this->loadEmailThreads();
        if ($token) {
            $thread = CommsEmailThread::query()
                ->where('comms_channel_id', $channel->id)
                ->where('token', $token)
                ->first();
            if ($thread) {
                $this->setActiveEmailThread((int) $thread->id);
            }
        } elseif ($this->activeEmailThreadId) {
            $this->loadEmailTimeline();
        }

        $this->emailMessage = '✅ E‑Mail gesendet.';
        $this->dispatch('comms:scroll-bottom');
    }

    private function loadEmailTimeline(): void
    {
        $this->emailTimeline = [];
        if (!$this->activeEmailThreadId) {
            return;
        }

        $inbound = CommsEmailInboundMail::query()
            ->where('thread_id', $this->activeEmailThreadId)
            ->get()
            ->map(fn (CommsEmailInboundMail $m) => [
                'direction' => 'inbound',
                'at' => $m->received_at?->toDateTimeString() ?: $m->created_at?->toDateTimeString(),
                'from' => $m->from,
                'to' => $m->to,
                'subject' => $m->subject,
                'html' => $m->html_body,
                'text' => $m->text_body,
            ]);

        $outbound = CommsEmailOutboundMail::query()
            ->where('thread_id', $this->activeEmailThreadId)
            ->get()
            ->map(fn (CommsEmailOutboundMail $m) => [
                'direction' => 'outbound',
                'at' => $m->sent_at?->toDateTimeString() ?: $m->created_at?->toDateTimeString(),
                'from' => $m->from,
                'to' => $m->to,
                'subject' => $m->subject,
                'html' => $m->html_body,
                'text' => $m->text_body,
            ]);

        $this->emailTimeline = $inbound
            ->concat($outbound)
            ->sortBy(fn ($x) => $x['at'] ?? '')
            ->values()
            ->all();

        $this->dispatch('comms:scroll-bottom');
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
            'newChannel.sender_local_part' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._%+\\-]+$/i'],
            'newChannel.sender_domain' => ['required', 'string', 'max:255'],
            'newChannel.name' => ['nullable', 'string', 'max:255'],
            'newChannel.visibility' => ['required', 'in:private,team'],
        ]);

        $type = (string) $this->newChannel['type'];
        $provider = (string) $this->newChannel['provider'];
        $local = trim((string) $this->newChannel['sender_local_part']);
        $selectedDomain = strtolower(trim((string) $this->newChannel['sender_domain']));
        $sender = $local . '@' . $selectedDomain;
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
            $configuredDomains = $conn->domains()->pluck('domain')->map(fn ($d) => strtolower((string) $d))->all();
            if (empty($configuredDomains)) {
                $this->channelsMessage = '⛔️ Bitte zuerst mindestens eine Domain in „Connections“ hinterlegen (Postmark Domains).';
                return;
            }
            if (!$selectedDomain || !in_array($selectedDomain, $configuredDomains, true)) {
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

        $this->newChannel['sender_local_part'] = '';
        $this->newChannel['sender_domain'] = '';
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

