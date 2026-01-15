<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailInboundMail;
use Platform\Core\Models\CommsEmailOutboundMail;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class ListEmailMessagesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.email_messages.GET';
    }

    public function getDescription(): string
    {
        return 'GET /comms/email_messages – Timeline (Inbound/Outbound) für thread_id. Nutze core.comms.email_messages.POST zum Senden/Reply.';
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
                'thread_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: Thread-ID.',
                ],
                'include_bodies' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bodies mit zurückgeben (text/html). Standard: true.',
                ],
            ],
            'required' => ['thread_id'],
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

            $threadId = (int) ($arguments['thread_id'] ?? 0);
            if ($threadId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'thread_id ist erforderlich.');
            }

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

            // Access like UI runtime:
            if ($channel->visibility === 'private' && (int) $channel->created_by_user_id !== (int) $context->user->id) {
                if (!$this->isRootTeamAdmin($context, $rootTeam)) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen privaten Kanal.');
                }
            }

            $includeBodies = array_key_exists('include_bodies', $arguments) ? (bool) $arguments['include_bodies'] : true;

            $inbound = CommsEmailInboundMail::query()
                ->where('thread_id', $threadId)
                ->get()
                ->map(fn (CommsEmailInboundMail $m) => [
                    'direction' => 'inbound',
                    'at' => $m->received_at?->toIso8601String() ?: $m->created_at?->toIso8601String(),
                    'from' => $m->from,
                    'to' => $m->to,
                    'subject' => $m->subject,
                    'text' => $includeBodies ? $m->text_body : null,
                    'html' => $includeBodies ? $m->html_body : null,
                ]);

            $outbound = CommsEmailOutboundMail::query()
                ->where('thread_id', $threadId)
                ->get()
                ->map(fn (CommsEmailOutboundMail $m) => [
                    'direction' => 'outbound',
                    'at' => $m->sent_at?->toIso8601String() ?: $m->created_at?->toIso8601String(),
                    'from' => $m->from,
                    'to' => $m->to,
                    'subject' => $m->subject,
                    'text' => $includeBodies ? $m->text_body : null,
                    'html' => $includeBodies ? $m->html_body : null,
                ]);

            $timeline = $inbound
                ->concat($outbound)
                ->sortBy(fn ($x) => $x['at'] ?? '')
                ->values()
                ->all();

            return ToolResult::success([
                'thread' => [
                    'id' => (int) $thread->id,
                    'token' => (string) $thread->token,
                    'subject' => (string) ($thread->subject ?: 'Ohne Betreff'),
                    'comms_channel_id' => (int) $thread->comms_channel_id,
                ],
                'messages' => $timeline,
                'count' => count($timeline),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Timeline: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['comms', 'email', 'messages', 'timeline'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

