<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsWhatsAppThread;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class UpdateWhatsAppThreadTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.whatsapp_threads.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /comms/whatsapp_threads – WhatsApp-Thread aktualisieren. Kann is_unread, context_model und context_model_id setzen.';
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
                    'description' => 'ERFORDERLICH: WhatsApp-Thread-ID.',
                ],
                'is_unread' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Thread als ungelesen (true) oder gelesen (false) markieren.',
                ],
                'context_model' => [
                    'type' => 'string',
                    'description' => 'Optional: Context-Model-Klasse zuweisen (z.B. "Platform\\HCM\\Models\\Applicant"). Setze null zum Entfernen.',
                ],
                'context_model_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Context-Model-ID zuweisen. Setze null zum Entfernen.',
                ],
                'contact_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Kontakt-Typ (polymorphic) zuweisen. Setze null zum Entfernen.',
                ],
                'contact_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Kontakt-ID (polymorphic) zuweisen. Setze null zum Entfernen.',
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

            // Find thread
            $thread = CommsWhatsAppThread::query()
                ->where('team_id', $rootTeam->id)
                ->whereKey($threadId)
                ->first();

            if (!$thread) {
                return ToolResult::error('NOT_FOUND', 'Thread nicht gefunden.');
            }

            // Verify channel access
            $channel = CommsChannel::query()
                ->where('team_id', $rootTeam->id)
                ->whereKey($thread->comms_channel_id)
                ->first();

            if (!$channel) {
                return ToolResult::error('NOT_FOUND', 'Channel zum Thread nicht gefunden.');
            }

            if ($channel->visibility === 'private' && (int) $channel->created_by_user_id !== (int) $context->user->id) {
                if (!$this->isRootTeamAdmin($context, $rootTeam)) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen privaten Kanal.');
                }
            }

            // Build updates
            $updates = [];

            if (array_key_exists('is_unread', $arguments)) {
                $updates['is_unread'] = (bool) $arguments['is_unread'];
            }

            if (array_key_exists('context_model', $arguments)) {
                $updates['context_model'] = $arguments['context_model'] ?: null;
            }

            if (array_key_exists('context_model_id', $arguments)) {
                $updates['context_model_id'] = $arguments['context_model_id'] ? (int) $arguments['context_model_id'] : null;
            }

            if (array_key_exists('contact_type', $arguments)) {
                $updates['contact_type'] = $arguments['contact_type'] ?: null;
            }

            if (array_key_exists('contact_id', $arguments)) {
                $updates['contact_id'] = $arguments['contact_id'] ? (int) $arguments['contact_id'] : null;
            }

            if (empty($updates)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Änderungen angegeben. Verfügbar: is_unread, context_model, context_model_id, contact_type, contact_id.');
            }

            $thread->update($updates);
            $thread->refresh();

            return ToolResult::success([
                'message' => 'WhatsApp-Thread aktualisiert.',
                'thread' => [
                    'id' => (int) $thread->id,
                    'comms_channel_id' => (int) $thread->comms_channel_id,
                    'remote_phone_number' => $thread->remote_phone_number,
                    'is_unread' => (bool) $thread->is_unread,
                    'context_model' => $thread->context_model,
                    'context_model_id' => $thread->context_model_id ? (int) $thread->context_model_id : null,
                    'contact_type' => $thread->contact_type,
                    'contact_id' => $thread->contact_id ? (int) $thread->contact_id : null,
                    'updated_at' => $thread->updated_at?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des WhatsApp-Threads: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['comms', 'whatsapp', 'threads', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
