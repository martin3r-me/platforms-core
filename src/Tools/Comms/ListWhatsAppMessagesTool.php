<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsWhatsAppMessage;
use Platform\Core\Models\CommsWhatsAppThread;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class ListWhatsAppMessagesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.whatsapp_messages.GET';
    }

    public function getDescription(): string
    {
        return 'GET /comms/whatsapp_messages – Nachrichten einer WhatsApp-Konversation abrufen. Benötigt thread_id. Optional: include_attachments=true für Datei-Anhänge.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Es wird auf das Root-Team aufgelöst.',
                    ],
                    'thread_id' => [
                        'type' => 'integer',
                        'description' => 'ERFORDERLICH: WhatsApp-Thread-ID.',
                    ],
                    'include_attachments' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Wenn true, werden Datei-Anhänge (ContextFiles) mit zurückgegeben.',
                    ],
                ],
                'required' => ['thread_id'],
            ]
        );
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

            $includeAttachments = (bool) ($arguments['include_attachments'] ?? false);

            // Find thread and verify access
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

            // Build query
            $query = CommsWhatsAppMessage::query()
                ->where('comms_whatsapp_thread_id', $threadId)
                ->with('sentByUser:id,first_name,last_name,email');

            $this->applyStandardFilters($query, $arguments, [
                'direction', 'message_type', 'status', 'created_at', 'sent_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['body']);
            $this->applyStandardSort($query, $arguments, [
                'created_at', 'sent_at',
            ], 'created_at', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);
            $messages = $result['data'];

            $items = $messages->map(function (CommsWhatsAppMessage $m) use ($includeAttachments) {
                $item = [
                    'id' => (int) $m->id,
                    'thread_id' => (int) $m->comms_whatsapp_thread_id,
                    'direction' => $m->direction,
                    'body' => $m->body,
                    'message_type' => $m->message_type,
                    'status' => $m->status,
                    'sent_at' => $m->sent_at?->toIso8601String(),
                    'delivered_at' => $m->delivered_at?->toIso8601String(),
                    'read_at' => $m->read_at?->toIso8601String(),
                    'created_at' => $m->created_at?->toIso8601String(),
                ];

                // Add sender info for outbound messages
                if ($m->direction === 'outbound' && $m->sentByUser) {
                    $item['sent_by'] = [
                        'id' => (int) $m->sentByUser->id,
                        'name' => trim($m->sentByUser->first_name . ' ' . $m->sentByUser->last_name) ?: $m->sentByUser->email,
                    ];
                }

                // Add template info if applicable
                if ($m->template_name) {
                    $item['template_name'] = $m->template_name;
                }

                // Add attachments if requested
                if ($includeAttachments) {
                    $item['attachments'] = $m->attachments;
                }

                return $item;
            })->values()->toArray();

            // Thread info for context
            $threadInfo = [
                'id' => (int) $thread->id,
                'comms_channel_id' => (int) $thread->comms_channel_id,
                'remote_phone_number' => $thread->remote_phone_number,
                'is_unread' => (bool) $thread->is_unread,
            ];

            return ToolResult::success([
                'thread' => $threadInfo,
                'messages' => $items,
                'count' => count($items),
                'pagination' => $result['pagination'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der WhatsApp-Nachrichten: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['comms', 'whatsapp', 'messages'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
