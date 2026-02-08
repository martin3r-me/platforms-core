<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailInboundMail;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Services\Comms\PostmarkEmailService;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class SendEmailMessageTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.email_messages.POST';
    }

    public function getDescription(): string
    {
        return 'POST /comms/email_messages – E‑Mail senden (Postmark). NEUER THREAD: { comms_channel_id, to, subject, body } (alle 4 erforderlich). REPLY: { thread_id, body } (to/subject automatisch aus Thread).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Es wird auf das Root-Team aufgelöst.',
                ],
                'comms_channel_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH für neuen Thread. Hole verfügbare Channels mit core.comms.channels.GET. Ignoriere für Reply (nutze thread_id).',
                ],
                'thread_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH für Reply. Ignoriere für neuen Thread (nutze comms_channel_id+to+subject).',
                ],
                'to' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH für neuen Thread (E‑Mail Adresse). Bei Reply optional (wird automatisch aus Thread abgeleitet).',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH für neuen Thread. Bei Reply optional (wird automatisch aus Thread.subject übernommen).',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Nachrichtentext (Plaintext, wird automatisch zu HTML konvertiert).',
                ],
            ],
            'required' => ['body'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveRootTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeam = $resolved['team'];

            $body = trim((string) ($arguments['body'] ?? ''));
            if ($body === '') {
                return ToolResult::error('VALIDATION_ERROR', 'body ist erforderlich.');
            }

            $threadId = (int) ($arguments['thread_id'] ?? 0);
            $isReply = $threadId > 0;

            $channel = null;
            $thread = null;

            if ($isReply) {
                $thread = CommsEmailThread::query()
                    ->where('team_id', $rootTeam->id)
                    ->whereKey($threadId)
                    ->first();
                if (!$thread) {
                    return ToolResult::error('NOT_FOUND', 'Thread nicht gefunden.');
                }

                $channel = CommsChannel::query()
                    ->where('team_id', $rootTeam->id)
                    ->whereKey($thread->comms_channel_id)
                    ->first();
                if (!$channel) {
                    return ToolResult::error('NOT_FOUND', 'Channel zum Thread nicht gefunden.');
                }
            } else {
                $channelId = (int) ($arguments['comms_channel_id'] ?? 0);
                if ($channelId <= 0) {
                    return ToolResult::error('VALIDATION_ERROR', 'Für einen neuen Thread benötigst du: comms_channel_id (hole mit core.comms.channels.GET), to, subject, body.');
                }
                $channel = CommsChannel::query()
                    ->where('team_id', $rootTeam->id)
                    ->whereKey($channelId)
                    ->first();
                if (!$channel) {
                    return ToolResult::error('NOT_FOUND', 'Channel nicht gefunden.');
                }
            }

            // Access: team channels usable by all; private channel only creator (or admin).
            if ($channel->visibility === 'private' && (int) $channel->created_by_user_id !== (int) $context->user->id) {
                if (!$this->isRootTeamAdmin($context, $rootTeam)) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen privaten Kanal.');
                }
            }

            // Resolve to/subject depending on mode
            $to = trim((string) ($arguments['to'] ?? ''));
            $subject = trim((string) ($arguments['subject'] ?? ''));

            if ($isReply) {
                if ($to === '') {
                    $to = (string) ($thread?->last_inbound_from_address ?? '');
                    if ($to === '') {
                        $lastInbound = CommsEmailInboundMail::query()
                            ->where('thread_id', $threadId)
                            ->orderByDesc('received_at')
                            ->first();
                        $to = $lastInbound?->from ? (string) $lastInbound->from : '';
                    }
                }
                if ($subject === '') {
                    $subject = (string) ($thread?->subject ?? '');
                }
                if ($to === '') {
                    return ToolResult::error('MISSING_TO', 'Reply: Empfänger konnte nicht aus dem Thread abgeleitet werden (kein last_inbound_from_address).');
                }
                if ($subject === '') {
                    $subject = 'Ohne Betreff';
                }
            } else {
                if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Neuer Thread: to ist erforderlich und muss eine E‑Mail Adresse sein.');
                }
                if ($subject === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'Neuer Thread: subject ist erforderlich.');
                }
            }

            // Minimal safe HTML: escape + nl2br
            $escaped = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $htmlBody = '<div style="font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; font-size: 14px; color: #111;">'
                . nl2br($escaped)
                . '</div>';

            /** @var PostmarkEmailService $svc */
            $svc = app(PostmarkEmailService::class);
            $token = $svc->send(
                $channel,
                $to,
                $subject,
                $htmlBody,
                null,
                [],
                [
                    'sender' => $context->user,
                    'token' => $thread?->token,
                    'is_reply' => $isReply,
                ]
            );

            // Resolve resulting thread for convenience
            $resultThread = CommsEmailThread::query()
                ->where('comms_channel_id', $channel->id)
                ->where('token', $token)
                ->first();

            // Kontext aus ToolContext-Metadata übernehmen (z.B. AutoPilot → Bewerber)
            if ($resultThread && !$resultThread->context_model) {
                $ctxModel = $context->metadata['context_model'] ?? null;
                $ctxModelId = $context->metadata['context_model_id'] ?? null;
                if ($ctxModel && $ctxModelId) {
                    $resultThread->update([
                        'context_model' => $ctxModel,
                        'context_model_id' => $ctxModelId,
                    ]);
                }
            }

            return ToolResult::success([
                'message' => 'E‑Mail gesendet.',
                'token' => $token,
                'thread' => $resultThread ? [
                    'id' => (int) $resultThread->id,
                    'comms_channel_id' => (int) $resultThread->comms_channel_id,
                    'subject' => (string) ($resultThread->subject ?: 'Ohne Betreff'),
                ] : null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Senden der E‑Mail: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['comms', 'email', 'send', 'postmark', 'outbound'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}

