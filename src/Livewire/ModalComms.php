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
use Platform\Core\Services\Comms\WhatsAppMetaService;
use Platform\Core\Services\Comms\WhatsAppChannelSyncService;
use Platform\Core\Models\CommsWhatsAppThread;
use Platform\Core\Models\CommsWhatsAppMessage;
use Platform\Integrations\Models\IntegrationsWhatsAppAccount;

/**
 * UI-only Comms v2 shell (no data, no logic).
 * Triggered from the navbar via the `open-modal-comms` event.
 */
class ModalComms extends Component
{
    public bool $open = false;

    // --- Kontext (via dispatch('comms', [...]) von Ticket/Task/etc.) ---
    public ?string $contextModel = null;
    public ?int $contextModelId = null;
    public ?string $contextSubject = null;
    public ?string $contextDescription = null;
    public ?string $contextUrl = null;
    public ?string $contextSource = null;
    public array $contextRecipients = [];
    public array $contextMeta = [];
    public array $contextCapabilities = [];

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
        'whatsapp_account_id' => null, // for WhatsApp channels
    ];

    public ?string $channelsMessage = null;

    /**
     * Available WhatsApp accounts for channel creation (user has access via owner or grant).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $availableWhatsAppAccounts = [];

    // --- Runtime Email (Kanäle Tab) ---
    /** @var array<int, array<string, mixed>> */
    public array $emailChannels = [];
    public ?int $activeEmailChannelId = null;
    public ?string $activeEmailChannelAddress = null;

    /** @var array<int, array<string, mixed>> */
    public array $emailThreads = [];
    public ?int $activeEmailThreadId = null;

    /**
     * Remember the last active thread per email channel (comms_channel_id => comms_email_threads.id).
     *
     * @var array<int, int>
     */
    public array $lastActiveEmailThreadByChannel = [];

    /** @var array<int, array<string, mixed>> */
    public array $emailTimeline = [];

    /** @var array<string, mixed> */
    public array $emailCompose = [
        'to' => '',
        'subject' => '',
        'body' => '',
    ];

    public bool $showAllThreads = false;

    public ?string $emailMessage = null;

    // --- Runtime WhatsApp (Kanäle Tab) ---
    /** @var array<int, array<string, mixed>> */
    public array $whatsappChannels = [];
    public ?int $activeWhatsAppChannelId = null;
    public ?string $activeWhatsAppChannelPhone = null;

    /** @var array<int, array<string, mixed>> */
    public array $whatsappThreads = [];
    public ?int $activeWhatsAppThreadId = null;

    /**
     * Remember the last active thread per WhatsApp channel (comms_channel_id => comms_whatsapp_threads.id).
     *
     * @var array<int, int>
     */
    public array $lastActiveWhatsAppThreadByChannel = [];

    /** @var array<int, array<string, mixed>> */
    public array $whatsappTimeline = [];

    /** @var array<string, mixed> */
    public array $whatsappCompose = [
        'to' => '',
        'body' => '',
    ];

    public ?string $whatsappMessage = null;

    // Debug WhatsApp Tab
    public array $debugWhatsAppAccounts = [];
    public array $debugWhatsAppChannels = [];
    public array $debugWhatsAppThreads = [];
    public array $debugInfo = [];

    #[On('comms')]
    public function setCommsContext(array $payload = []): void
    {
        $this->contextModel       = $payload['model']       ?? null;
        $this->contextModelId     = $payload['modelId']      ?? null;
        $this->contextSubject     = $payload['subject']      ?? null;
        $this->contextDescription = $payload['description']  ?? null;
        $this->contextUrl         = $payload['url']          ?? null;
        $this->contextSource      = $payload['source']       ?? null;
        $this->contextRecipients  = $payload['recipients']   ?? [];
        $this->contextMeta        = $payload['meta']         ?? [];
        $this->contextCapabilities = $payload['capabilities'] ?? [];
    }

    public function hasContext(): bool
    {
        return !empty($this->contextModel) && !empty($this->contextModelId);
    }

    #[On('open-modal-comms')]
    public function openModal(array $payload = []): void
    {
        $this->open = true;
        $this->loadPostmarkConnection();
        $this->loadChannels();
        $this->loadEmailRuntime();
        $this->loadWhatsAppRuntime();
        $this->loadDebugWhatsApp();
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

        $rememberedThreadId = (int) ($this->lastActiveEmailThreadByChannel[(int) $this->activeEmailChannelId] ?? 0);
        $useRemembered = false;

        $this->emailCompose['subject'] = '';
        $this->emailCompose['body'] = '';
        $this->emailCompose['to'] = '';

        // Prefer restoring the last active thread for this channel (if it still exists).
        $this->activeEmailThreadId = null;
        if ($rememberedThreadId > 0 && $this->activeEmailChannelId) {
            $exists = CommsEmailThread::query()
                ->where('comms_channel_id', $this->activeEmailChannelId)
                ->whereKey($rememberedThreadId)
                ->exists();
            if ($exists) {
                $this->activeEmailThreadId = $rememberedThreadId;
                $useRemembered = true;
            }
        }

        $this->loadEmailThreads();
        if ($useRemembered && $this->activeEmailThreadId) {
            // loadEmailThreads() clears timeline; restore the remembered thread timeline explicitly
            $this->setActiveEmailThread((int) $this->activeEmailThreadId);
        }
        $this->dispatch('comms:scroll-bottom');
    }

    public function updatingActiveEmailChannelId($value): void
    {
        // Persist last active thread for the "old" channel before switching.
        if ($this->activeEmailChannelId && $this->activeEmailThreadId) {
            $this->lastActiveEmailThreadByChannel[(int) $this->activeEmailChannelId] = (int) $this->activeEmailThreadId;
        }
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

    public function toggleShowAllThreads(): void
    {
        $this->showAllThreads = !$this->showAllThreads;
        $this->loadEmailThreads();
    }

    public function loadEmailThreads(): void
    {
        $this->emailThreads = [];
        $this->emailTimeline = [];

        if (!$this->activeEmailChannelId) {
            return;
        }

        $query = CommsEmailThread::query()
            ->where('comms_channel_id', $this->activeEmailChannelId);

        if ($this->hasContext() && !$this->showAllThreads) {
            $query->where('context_model', $this->contextModel)
                  ->where('context_model_id', $this->contextModelId);
        }

        $threads = $query
            ->withCount(['inboundMails', 'outboundMails'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $this->emailThreads = $threads->map(fn (CommsEmailThread $t) => [
            'id' => (int) $t->id,
            'subject' => (string) ($t->subject ?: 'Ohne Betreff'),
            'counterpart' => (string) ($t->last_inbound_from_address ?: $t->last_outbound_to_address ?: ''),
            'messages_count' => (int) (($t->inbound_mails_count ?? 0) + ($t->outbound_mails_count ?? 0)),
            'last_direction' => ($t->last_inbound_at && (!$t->last_outbound_at || $t->last_inbound_at->greaterThanOrEqualTo($t->last_outbound_at)))
                ? 'inbound'
                : (($t->last_outbound_at || $t->last_inbound_at) ? 'outbound' : null),
            'last_at' => ($t->last_inbound_at || $t->last_outbound_at)
                ? ((($t->last_inbound_at && (!$t->last_outbound_at || $t->last_inbound_at->greaterThanOrEqualTo($t->last_outbound_at))))
                    ? $t->last_inbound_at?->format('d.m. H:i')
                    : $t->last_outbound_at?->format('d.m. H:i'))
                : ($t->updated_at?->format('d.m. H:i')),
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
        $this->emailCompose['body'] = '';

        // Prefill from context if available
        if ($this->hasContext()) {
            $this->emailCompose['subject'] = (string) ($this->contextSubject ?? '');
            $this->emailCompose['to'] = (string) (($this->contextRecipients[0] ?? '') ?: $this->emailCompose['to']);
        } else {
            $this->emailCompose['subject'] = '';
        }

        $this->dispatch('comms:scroll-bottom');
    }

    public function sendEmail(): void
    {
        $this->emailMessage = null;
        $wasNewThread = !$this->activeEmailThreadId;

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->emailMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        if (!$this->activeEmailChannelId) {
            $this->emailMessage = '⛔️ Kein E‑Mail Kanal ausgewählt.';
            return;
        }

        try {
            $isReply = (bool) $this->activeEmailThreadId;
            $this->validate([
                'emailCompose.to' => [$isReply ? 'nullable' : 'required', 'email', 'max:255'],
                'emailCompose.body' => ['required', 'string', 'min:1'],
                'emailCompose.subject' => [$isReply ? 'nullable' : 'required', 'string', 'max:255'],
            ]);
        } catch (ValidationException $e) {
            $this->emailMessage = '⛔️ Bitte Eingaben prüfen.';
            return;
        }

        $channel = CommsChannel::query()
            ->whereKey($this->activeEmailChannelId)
            ->where('type', 'email')
            ->where('provider', 'postmark')
            ->where('is_active', true)
            ->first();
        if (!$channel) {
            $this->emailMessage = '⛔️ E‑Mail Kanal nicht gefunden.';
            return;
        }

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
                return;
            }
        }

        try {
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
        } catch (\Throwable $e) {
            $this->emailMessage = '⛔️ Versand fehlgeschlagen: ' . $e->getMessage();
            return;
        }

        $this->emailCompose['body'] = '';
        if ($wasNewThread) {
            $this->emailCompose['subject'] = '';
            $this->emailCompose['to'] = '';
        }

        // Link new thread to context if applicable
        if ($wasNewThread && $this->hasContext() && $token) {
            $newThread = CommsEmailThread::query()
                ->where('comms_channel_id', $channel->id)
                ->where('token', $token)
                ->first();
            if ($newThread && !$newThread->context_model) {
                $newThread->update([
                    'context_model' => $this->contextModel,
                    'context_model_id' => $this->contextModelId,
                ]);
            }
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

    // -------------------------------------------------------------------------
    // WhatsApp Runtime Methods
    // -------------------------------------------------------------------------

    public function loadWhatsAppRuntime(): void
    {
        $this->whatsappMessage = null;
        $this->whatsappChannels = [];
        $this->whatsappThreads = [];
        $this->whatsappTimeline = [];

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        // Channels visible to the current user (type=whatsapp, provider=whatsapp_meta):
        // - team channels (shared)
        // - private channels created by user
        $channelQuery = fn () => CommsChannel::query()
            ->where('team_id', $rootTeam->id)
            ->where('type', 'whatsapp')
            ->where('provider', 'whatsapp_meta')
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

        $channels = $channelQuery();

        // Fallback: If no channels found, try syncing from integrations
        if ($channels->isEmpty()) {
            try {
                $syncService = app(WhatsAppChannelSyncService::class);
                $syncService->syncForTeam($rootTeam);

                // Re-run query after sync
                $channels = $channelQuery();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[ModalComms] WhatsApp sync failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->whatsappChannels = $channels->map(fn (CommsChannel $c) => [
            'id' => (int) $c->id,
            'label' => (string) $c->sender_identifier,
            'name' => $c->name ? (string) $c->name : null,
        ])->all();

        if (!$this->activeWhatsAppChannelId && $channels->isNotEmpty()) {
            $this->activeWhatsAppChannelId = (int) $channels->first()->id;
        }

        $this->refreshActiveWhatsAppChannelLabel();
        $this->loadWhatsAppThreads();
    }

    public function updatedActiveWhatsAppChannelId(): void
    {
        $this->refreshActiveWhatsAppChannelLabel();

        $rememberedThreadId = (int) ($this->lastActiveWhatsAppThreadByChannel[(int) $this->activeWhatsAppChannelId] ?? 0);
        $useRemembered = false;

        $this->whatsappCompose['body'] = '';
        $this->whatsappCompose['to'] = '';

        // Prefer restoring the last active thread for this channel (if it still exists).
        $this->activeWhatsAppThreadId = null;
        if ($rememberedThreadId > 0 && $this->activeWhatsAppChannelId) {
            $exists = CommsWhatsAppThread::query()
                ->where('comms_channel_id', $this->activeWhatsAppChannelId)
                ->whereKey($rememberedThreadId)
                ->exists();
            if ($exists) {
                $this->activeWhatsAppThreadId = $rememberedThreadId;
                $useRemembered = true;
            }
        }

        $this->loadWhatsAppThreads();
        if ($useRemembered && $this->activeWhatsAppThreadId) {
            $this->setActiveWhatsAppThread((int) $this->activeWhatsAppThreadId);
        }
        $this->dispatch('comms:scroll-bottom');
    }

    public function updatingActiveWhatsAppChannelId($value): void
    {
        // Persist last active thread for the "old" channel before switching.
        if ($this->activeWhatsAppChannelId && $this->activeWhatsAppThreadId) {
            $this->lastActiveWhatsAppThreadByChannel[(int) $this->activeWhatsAppChannelId] = (int) $this->activeWhatsAppThreadId;
        }
    }

    private function refreshActiveWhatsAppChannelLabel(): void
    {
        $this->activeWhatsAppChannelPhone = null;
        if (!$this->activeWhatsAppChannelId) {
            return;
        }
        foreach ($this->whatsappChannels as $c) {
            if ((int) ($c['id'] ?? 0) === (int) $this->activeWhatsAppChannelId) {
                $this->activeWhatsAppChannelPhone = (string) ($c['label'] ?? null);
                return;
            }
        }
    }

    public function loadWhatsAppThreads(): void
    {
        $this->whatsappThreads = [];
        $this->whatsappTimeline = [];

        if (!$this->activeWhatsAppChannelId) {
            return;
        }

        $query = CommsWhatsAppThread::query()
            ->where('comms_channel_id', $this->activeWhatsAppChannelId);

        if ($this->hasContext() && !$this->showAllThreads) {
            $query->where('context_model', $this->contextModel)
                  ->where('context_model_id', $this->contextModelId);
        }

        $threads = $query
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $this->whatsappThreads = $threads->map(fn (CommsWhatsAppThread $t) => [
            'id' => (int) $t->id,
            'remote_phone' => (string) ($t->remote_phone_number ?: '—'),
            'messages_count' => (int) ($t->messages_count ?? 0),
            'last_message_preview' => (string) ($t->last_message_preview ?: ''),
            'is_unread' => (bool) $t->is_unread,
            'last_direction' => ($t->last_inbound_at && (!$t->last_outbound_at || $t->last_inbound_at->greaterThanOrEqualTo($t->last_outbound_at)))
                ? 'inbound'
                : (($t->last_outbound_at || $t->last_inbound_at) ? 'outbound' : null),
            'last_at' => ($t->last_inbound_at || $t->last_outbound_at)
                ? ((($t->last_inbound_at && (!$t->last_outbound_at || $t->last_inbound_at->greaterThanOrEqualTo($t->last_outbound_at))))
                    ? $t->last_inbound_at?->format('d.m. H:i')
                    : $t->last_outbound_at?->format('d.m. H:i'))
                : ($t->updated_at?->format('d.m. H:i')),
        ])->all();

        if (!$this->activeWhatsAppThreadId && $threads->isNotEmpty()) {
            $this->setActiveWhatsAppThread((int) $threads->first()->id);
        }
    }

    public function setActiveWhatsAppThread(int $threadId): void
    {
        $this->activeWhatsAppThreadId = $threadId;
        $this->loadWhatsAppTimeline();

        // Pre-fill "to" from thread phone number
        $thread = CommsWhatsAppThread::query()->whereKey($threadId)->first();
        if ($thread?->remote_phone_number) {
            $this->whatsappCompose['to'] = (string) $thread->remote_phone_number;
        }

        // Mark thread as read
        $thread?->markAsRead();

        $this->dispatch('comms:scroll-bottom');
    }

    private function loadWhatsAppTimeline(): void
    {
        $this->whatsappTimeline = [];
        if (!$this->activeWhatsAppThreadId) {
            return;
        }

        $messages = CommsWhatsAppMessage::query()
            ->where('comms_whatsapp_thread_id', $this->activeWhatsAppThreadId)
            ->orderByRaw('COALESCE(sent_at, created_at) ASC')
            ->get();

        $this->whatsappTimeline = $messages->map(fn (CommsWhatsAppMessage $m) => [
            'id' => (int) $m->id,
            'direction' => (string) ($m->direction ?? 'outbound'),
            'body' => (string) ($m->body ?? ''),
            'message_type' => (string) ($m->message_type ?? 'text'),
            'media_display_type' => (string) $m->media_display_type,
            'status' => (string) ($m->status ?? ''),
            'at' => $m->sent_at?->format('H:i') ?: $m->created_at?->format('H:i'),
            'full_at' => $m->sent_at?->format('d.m.Y H:i') ?: $m->created_at?->format('d.m.Y H:i'),
            'sent_by' => $m->sentByUser?->name ?? null,
            'has_media' => $m->hasMedia(),
            'attachments' => $m->attachments ?? [],
        ])->all();

        $this->dispatch('comms:scroll-bottom');
    }

    public function startNewWhatsAppThread(): void
    {
        $this->activeWhatsAppThreadId = null;
        $this->whatsappTimeline = [];
        $this->whatsappCompose['body'] = '';

        // Prefill from context if available
        if ($this->hasContext()) {
            // Try to get phone from context recipients
            $phone = (string) (($this->contextRecipients[0] ?? '') ?: $this->whatsappCompose['to']);
            // Only use if it looks like a phone number
            if (preg_match('/^\+?\d{7,}$/', preg_replace('/[\s\-()]/', '', $phone))) {
                $this->whatsappCompose['to'] = $phone;
            }
        } else {
            $this->whatsappCompose['to'] = '';
        }

        $this->dispatch('comms:scroll-bottom');
    }

    public function sendWhatsApp(): void
    {
        $this->whatsappMessage = null;
        $wasNewThread = !$this->activeWhatsAppThreadId;

        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->whatsappMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        if (!$this->activeWhatsAppChannelId) {
            $this->whatsappMessage = '⛔️ Kein WhatsApp Kanal ausgewählt.';
            return;
        }

        $isReply = (bool) $this->activeWhatsAppThreadId;
        try {
            $this->validate([
                'whatsappCompose.to' => [$isReply ? 'nullable' : 'required', 'string', 'max:32'],
                'whatsappCompose.body' => ['required', 'string', 'min:1'],
            ]);
        } catch (ValidationException $e) {
            $this->whatsappMessage = '⛔️ Bitte Eingaben prüfen.';
            return;
        }

        $channel = CommsChannel::query()
            ->whereKey($this->activeWhatsAppChannelId)
            ->where('type', 'whatsapp')
            ->where('provider', 'whatsapp_meta')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            $this->whatsappMessage = '⛔️ WhatsApp Kanal nicht gefunden.';
            return;
        }

        $to = (string) ($this->whatsappCompose['to'] ?? '');

        // For replies, resolve "to" from thread if not provided
        if ($this->activeWhatsAppThreadId && trim($to) === '') {
            $thread = CommsWhatsAppThread::query()->whereKey($this->activeWhatsAppThreadId)->first();
            if ($thread?->remote_phone_number) {
                $to = (string) $thread->remote_phone_number;
            }
            $this->whatsappCompose['to'] = $to;
        }

        if (trim($to) === '') {
            $this->whatsappMessage = '⛔️ Kein Empfänger angegeben.';
            return;
        }

        try {
            /** @var WhatsAppMetaService $svc */
            $svc = app(WhatsAppMetaService::class);
            $message = $svc->sendText(
                $channel,
                $to,
                (string) $this->whatsappCompose['body'],
                $user
            );
        } catch (\Throwable $e) {
            $this->whatsappMessage = '⛔️ Versand fehlgeschlagen: ' . $e->getMessage();
            return;
        }

        $this->whatsappCompose['body'] = '';
        if ($wasNewThread) {
            $this->whatsappCompose['to'] = '';
        }

        // Link new thread to context if applicable
        if ($wasNewThread && $this->hasContext() && $message?->thread) {
            $newThread = $message->thread;
            if ($newThread && !$newThread->context_model) {
                $newThread->update([
                    'context_model' => $this->contextModel,
                    'context_model_id' => $this->contextModelId,
                ]);
            }
        }

        // Refresh threads & select the thread for the sent message
        $this->loadWhatsAppThreads();
        if ($message?->thread) {
            $this->setActiveWhatsAppThread((int) $message->thread->id);
        } elseif ($this->activeWhatsAppThreadId) {
            $this->loadWhatsAppTimeline();
        }

        $this->whatsappMessage = '✅ Nachricht gesendet.';
        $this->dispatch('comms:scroll-bottom');
    }

    public function deleteWhatsAppThread(int $threadId): void
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user || !$team) {
            $this->whatsappMessage = '⛔️ Kein Team-Kontext gefunden.';
            return;
        }

        /** @var Team $rootTeam */
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $thread = CommsWhatsAppThread::query()
            ->where('team_id', $rootTeam->id)
            ->whereKey($threadId)
            ->first();

        if (!$thread) {
            $this->whatsappMessage = '⛔️ Thread nicht gefunden.';
            return;
        }

        $channel = CommsChannel::query()
            ->where('team_id', $rootTeam->id)
            ->whereKey($thread->comms_channel_id)
            ->first();

        if (!$channel) {
            $this->whatsappMessage = '⛔️ Kanal zum Thread nicht gefunden.';
            return;
        }

        // Permission: team channels: only owner/admin; private channels: owner/admin or channel creator
        if ($channel->visibility === 'team') {
            if (!$this->canManageProviderConnections()) {
                $this->whatsappMessage = '⛔️ Keine Berechtigung (teamweite Kanäle nur Owner/Admin).';
                return;
            }
        } else {
            if (!$this->canManageProviderConnections() && (int) $channel->created_by_user_id !== (int) $user->id) {
                $this->whatsappMessage = '⛔️ Keine Berechtigung (privater Kanal gehört einem anderen User).';
                return;
            }
        }

        // Soft-delete thread (messages remain for audit)
        $thread->delete();

        if ((int) $this->activeWhatsAppThreadId === (int) $threadId) {
            $this->activeWhatsAppThreadId = null;
            $this->whatsappTimeline = [];
        }

        $this->whatsappMessage = '✅ Thread gelöscht.';
        $this->loadWhatsAppThreads();
        $this->dispatch('comms:scroll-bottom');
    }

    /**
     * Refresh timelines for polling (only when modal is open).
     */
    public function refreshTimelines(): void
    {
        if (!$this->open) {
            return;
        }

        // Only refresh the active timeline
        if ($this->activeWhatsAppThreadId) {
            $this->loadWhatsAppTimeline();
        }
        if ($this->activeEmailThreadId) {
            $this->loadEmailTimeline();
        }
    }

    public function loadDebugWhatsApp(): void
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        $rootTeam = $team && method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        // Debug Info
        $this->debugInfo = [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'team_id' => $team?->id,
            'team_name' => $team?->name,
            'root_team_id' => $rootTeam?->id,
            'root_team_name' => $rootTeam?->name,
        ];

        // Alle IntegrationsWhatsAppAccount (ohne Filter)
        $this->debugWhatsAppAccounts = IntegrationsWhatsAppAccount::query()
            ->with('integrationConnection.ownerUser')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'phone_number' => $a->phone_number,
                'phone_number_id' => $a->phone_number_id,
                'title' => $a->title,
                'active' => $a->active,
                'connection_id' => $a->integration_connection_id,
                'owner_user_id' => $a->integrationConnection?->ownerUser?->id,
                'owner_team_id' => $a->integrationConnection?->ownerUser?->team_id,
            ])
            ->all();

        // Alle CommsChannel type=whatsapp (ohne Filter)
        $this->debugWhatsAppChannels = CommsChannel::query()
            ->where('type', 'whatsapp')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'team_id' => $c->team_id,
                'sender_identifier' => $c->sender_identifier,
                'name' => $c->name,
                'visibility' => $c->visibility,
                'is_active' => $c->is_active,
                'meta' => $c->meta,
            ])
            ->all();

        // Alle CommsWhatsAppThread (ohne Filter)
        $this->debugWhatsAppThreads = CommsWhatsAppThread::query()
            ->withCount('messages')
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'channel_id' => $t->comms_channel_id,
                'team_id' => $t->team_id,
                'remote_phone' => $t->remote_phone_number,
                'messages_count' => $t->messages_count,
                'updated_at' => $t->updated_at?->format('d.m.Y H:i'),
            ])
            ->all();
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

        // Load both email and whatsapp channels
        $this->channels = CommsChannel::query()
            ->where('team_id', $rootTeam->id)
            ->whereIn('type', ['email', 'whatsapp'])
            ->orderByDesc('is_active')
            ->orderBy('type')
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

        // Load available WhatsApp accounts for channel creation
        $this->loadAvailableWhatsAppAccounts();
    }

    /**
     * Load WhatsApp accounts that the current user has access to (as owner or via grant).
     */
    public function loadAvailableWhatsAppAccounts(): void
    {
        $this->availableWhatsAppAccounts = [];

        $user = Auth::user();
        if (!$user) {
            return;
        }

        // Get all IntegrationConnections where user is owner or has a grant
        $accounts = IntegrationsWhatsAppAccount::query()
            ->whereHas('integrationConnection', function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                  ->orWhereHas('grants', function ($gq) use ($user) {
                      $gq->where('grantee_user_id', $user->id);
                  });
            })
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->with('integrationConnection.ownerUser')
            ->get();

        $this->availableWhatsAppAccounts = $accounts->map(fn (IntegrationsWhatsAppAccount $a) => [
            'id' => (int) $a->id,
            'phone_number' => (string) $a->phone_number,
            'title' => $a->title ? (string) $a->title : null,
            'label' => $a->title
                ? "{$a->title} ({$a->phone_number})"
                : (string) $a->phone_number,
            'owner' => $a->integrationConnection?->ownerUser?->name ?? '—',
        ])->all();
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

        $type = (string) ($this->newChannel['type'] ?? 'email');
        $visibility = (string) ($this->newChannel['visibility'] ?? 'private');

        if ($visibility === 'team' && !$this->canCreateTeamSharedChannel()) {
            $this->channelsMessage = '⛔️ Teamweite Kanäle dürfen nur Owner/Admin des Root-Teams anlegen.';
            return;
        }

        // Handle different channel types
        if ($type === 'whatsapp') {
            $this->createWhatsAppChannel($rootTeam, $user, $visibility);
        } else {
            $this->createEmailChannel($rootTeam, $user, $visibility);
        }
    }

    private function createEmailChannel(Team $rootTeam, $user, string $visibility): void
    {
        $this->validate([
            'newChannel.type' => ['required', 'string', 'max:32'],
            'newChannel.provider' => ['required', 'string', 'max:64'],
            'newChannel.sender_local_part' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._%+\\-]+$/i'],
            'newChannel.sender_domain' => ['required', 'string', 'max:255'],
            'newChannel.name' => ['nullable', 'string', 'max:255'],
            'newChannel.visibility' => ['required', 'in:private,team'],
        ]);

        $provider = (string) $this->newChannel['provider'];
        $local = trim((string) $this->newChannel['sender_local_part']);
        $selectedDomain = strtolower(trim((string) $this->newChannel['sender_domain']));
        $sender = $local . '@' . $selectedDomain;

        if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            $this->channelsMessage = '⛔️ Bitte eine gültige E‑Mail-Adresse als Absender eintragen.';
            return;
        }

        $connectionId = null;
        if ($provider === 'postmark') {
            $conn = CommsProviderConnection::forTeamProvider($rootTeam, 'postmark');
            if (!$conn) {
                $this->channelsMessage = '⛔️ Keine Postmark Connection gefunden. Bitte zuerst im Tab „Connections" speichern.';
                return;
            }
            $connectionId = $conn->id;

            // Absender-Domain MUSS in hinterlegten Domains enthalten sein.
            $configuredDomains = $conn->domains()->pluck('domain')->map(fn ($d) => strtolower((string) $d))->all();
            if (empty($configuredDomains)) {
                $this->channelsMessage = '⛔️ Bitte zuerst mindestens eine Domain in „Connections" hinterlegen (Postmark Domains).';
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
                'type' => 'email',
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
        $this->channelsMessage = '✅ E-Mail Kanal angelegt.';
    }

    private function createWhatsAppChannel(Team $rootTeam, $user, string $visibility): void
    {
        $this->validate([
            'newChannel.whatsapp_account_id' => ['required', 'integer'],
            'newChannel.name' => ['nullable', 'string', 'max:255'],
            'newChannel.visibility' => ['required', 'in:private,team'],
        ]);

        $accountId = (int) $this->newChannel['whatsapp_account_id'];

        // Verify the user has access to this account
        $account = IntegrationsWhatsAppAccount::query()
            ->whereKey($accountId)
            ->whereHas('integrationConnection', function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                  ->orWhereHas('grants', function ($gq) use ($user) {
                      $gq->where('grantee_user_id', $user->id);
                  });
            })
            ->first();

        if (!$account) {
            $this->channelsMessage = '⛔️ WhatsApp Account nicht gefunden oder keine Berechtigung.';
            return;
        }

        if (!$account->phone_number) {
            $this->channelsMessage = '⛔️ Der gewählte WhatsApp Account hat keine Telefonnummer.';
            return;
        }

        // Get or create the WhatsApp Meta provider connection for this team
        $connection = CommsProviderConnection::firstOrCreate(
            [
                'team_id' => $rootTeam->id,
                'provider' => 'whatsapp_meta',
            ],
            [
                'name' => 'WhatsApp Meta',
                'is_active' => true,
                'credentials' => [],
            ]
        );

        try {
            CommsChannel::create([
                'team_id' => $rootTeam->id,
                'created_by_user_id' => $user->id,
                'comms_provider_connection_id' => $connection->id,
                'type' => 'whatsapp',
                'provider' => 'whatsapp_meta',
                'name' => trim((string) ($this->newChannel['name'] ?? '')) ?: ($account->title ?: $account->phone_number),
                'sender_identifier' => $account->phone_number,
                'visibility' => $visibility,
                'is_active' => true,
                'meta' => [
                    'integrations_whatsapp_account_id' => $account->id,
                    'phone_number_id' => $account->phone_number_id,
                    'access_token' => $account->access_token,
                ],
            ]);
        } catch (QueryException $e) {
            $this->channelsMessage = '⛔️ Dieser WhatsApp Kanal existiert bereits.';
            return;
        }

        $this->newChannel['whatsapp_account_id'] = null;
        $this->newChannel['name'] = '';
        $this->newChannel['visibility'] = 'private';
        $this->newChannel['type'] = 'email'; // Reset to default

        $this->loadChannels();
        $this->loadWhatsAppRuntime(); // Refresh WhatsApp runtime
        $this->channelsMessage = '✅ WhatsApp Kanal angelegt.';
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

