<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class DeleteEmailThreadTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.email_threads.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /comms/email_threads/{id} - Löscht einen E‑Mail Thread (hard delete, damit DB sauber bleibt). Parameter: thread_id (required), confirm=true (required), team_id (optional).';
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
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
            ],
            'required' => ['thread_id', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', 'Bitte bestätige mit confirm=true.');
            }

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

            $isAdmin = $this->isRootTeamAdmin($context, $rootTeam);
            $isCreator = (int) $channel->created_by_user_id === (int) $context->user->id;

            // Permission (same as UI):
            // - team channels: only owner/admin
            // - private channels: owner/admin or channel creator
            if ($channel->visibility === 'team') {
                if (!$isAdmin) {
                    return ToolResult::error('ACCESS_DENIED', 'Teamweite Kanäle: Thread löschen nur für Owner/Admin.');
                }
            } else {
                if (!$isAdmin && !$isCreator) {
                    return ToolResult::error('ACCESS_DENIED', 'Privater Kanal gehört einem anderen User.');
                }
            }

            $id = (int) $thread->id;
            $token = (string) $thread->token;
            $subject = (string) ($thread->subject ?: 'Ohne Betreff');

            // Hard-delete to keep DB clean (FK cascades should remove mails/attachments).
            $thread->forceDelete();

            return ToolResult::success([
                'thread_id' => $id,
                'token' => $token,
                'subject' => $subject,
                'message' => 'Thread gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Threads: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['comms', 'email', 'threads', 'delete'],
            'risk_level' => 'destructive',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
            'confirmation_required' => true,
        ];
    }
}

