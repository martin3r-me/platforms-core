<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class UpdateEmailThreadTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.email_threads.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /comms/email_threads/{id} - Aktualisiert den Betreff eines Threads (nur solange noch keine Mails im Thread existieren). Parameter: thread_id (required), subject (required).';
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
                'subject' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Betreff (1..255).',
                ],
            ],
            'required' => ['thread_id', 'subject'],
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

            $subject = trim((string) ($arguments['subject'] ?? ''));
            if ($subject === '' || strlen($subject) > 255) {
                return ToolResult::error('VALIDATION_ERROR', 'subject ist erforderlich (max. 255).');
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

            // Access like UI:
            // team channel: any member can read, but update is only meaningful for own/private usage;
            // we still restrict private channels to creator/admin.
            if ($channel->visibility === 'private' && (int) $channel->created_by_user_id !== (int) $context->user->id) {
                if (!$this->isRootTeamAdmin($context, $rootTeam)) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen privaten Kanal.');
                }
            }

            // Subject is immutable once mails exist.
            if ($thread->inboundMails()->exists() || $thread->outboundMails()->exists()) {
                return ToolResult::error('IMMUTABLE', 'Der Betreff kann nicht mehr geändert werden, sobald der Thread Nachrichten enthält.');
            }

            $thread->subject = $subject;
            $thread->save();

            return ToolResult::success([
                'thread' => [
                    'id' => (int) $thread->id,
                    'comms_channel_id' => (int) $thread->comms_channel_id,
                    'token' => (string) $thread->token,
                    'subject' => (string) ($thread->subject ?: 'Ohne Betreff'),
                    'updated_at' => $thread->updated_at?->toIso8601String(),
                ],
                'message' => 'Thread aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Threads: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['comms', 'email', 'threads', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}

