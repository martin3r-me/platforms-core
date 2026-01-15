<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class CreateEmailThreadTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.email_threads.POST';
    }

    public function getDescription(): string
    {
        return 'POST /comms/email_threads - Legt einen neuen E‑Mail Thread an (Root-Team Scope). Parameter: comms_channel_id (required), subject (optional).';
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
                    'description' => 'ERFORDERLICH: Channel-ID (comms_channels.id).',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'Optional: Betreff (max. 255).',
                ],
            ],
            'required' => ['comms_channel_id'],
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

            $channelId = (int) ($arguments['comms_channel_id'] ?? 0);
            if ($channelId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'comms_channel_id ist erforderlich.');
            }

            $channel = CommsChannel::query()
                ->where('team_id', $rootTeam->id)
                ->whereKey($channelId)
                ->first();

            if (!$channel) {
                return ToolResult::error('NOT_FOUND', 'Channel nicht gefunden.');
            }

            // Access like UI runtime
            if ($channel->visibility === 'private' && (int) $channel->created_by_user_id !== (int) $context->user->id) {
                if (!$this->isRootTeamAdmin($context, $rootTeam)) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen privaten Kanal.');
                }
            }

            $subject = trim((string) ($arguments['subject'] ?? ''));
            if ($subject !== '' && strlen($subject) > 255) {
                return ToolResult::error('VALIDATION_ERROR', 'subject ist zu lang (max. 255).');
            }

            $thread = CommsEmailThread::create([
                'team_id' => $rootTeam->id,
                'comms_channel_id' => $channel->id,
                'token' => str()->ulid()->toBase32(),
                'subject' => $subject !== '' ? $subject : null,
            ]);

            return ToolResult::success([
                'thread' => [
                    'id' => (int) $thread->id,
                    'team_id' => (int) $thread->team_id,
                    'comms_channel_id' => (int) $thread->comms_channel_id,
                    'token' => (string) $thread->token,
                    'subject' => (string) ($thread->subject ?: 'Ohne Betreff'),
                    'created_at' => $thread->created_at?->toIso8601String(),
                ],
                'message' => 'Thread angelegt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Threads: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['comms', 'email', 'threads', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}

