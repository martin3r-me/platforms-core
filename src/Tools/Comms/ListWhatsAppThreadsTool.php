<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsWhatsAppConversationThread;
use Platform\Core\Models\CommsWhatsAppThread;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class ListWhatsAppThreadsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.whatsapp_threads.GET';
    }

    public function getDescription(): string
    {
        return 'GET /comms/whatsapp_threads – WhatsApp-Konversationen auflisten. Optional filtern nach comms_channel_id, is_unread, context_model/context_model_id. Für Reply: thread_id wählen und mit core.comms.whatsapp_messages.POST senden.';
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
                    'comms_channel_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Nur Threads eines bestimmten WhatsApp-Channels.',
                    ],
                    'is_unread' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur ungelesene (true) oder gelesene (false) Threads.',
                    ],
                    'context_model' => [
                        'type' => 'string',
                        'description' => 'Optional: Context-Model-Klasse (z.B. "Platform\\HCM\\Models\\Applicant").',
                    ],
                    'context_model_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Context-Model-ID (zusammen mit context_model).',
                    ],
                ],
                'required' => [],
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

            $channelId = isset($arguments['comms_channel_id']) ? (int) $arguments['comms_channel_id'] : null;
            $isUnread = isset($arguments['is_unread']) ? (bool) $arguments['is_unread'] : null;
            $contextModel = isset($arguments['context_model']) ? (string) $arguments['context_model'] : null;
            $contextModelId = isset($arguments['context_model_id']) ? (int) $arguments['context_model_id'] : null;

            // If a specific channel is requested, verify access
            if ($channelId) {
                $channel = CommsChannel::query()
                    ->where('team_id', $rootTeam->id)
                    ->whereKey($channelId)
                    ->first();

                if (!$channel) {
                    return ToolResult::error('NOT_FOUND', 'Channel nicht gefunden.');
                }

                if ($channel->type !== 'whatsapp') {
                    return ToolResult::error('VALIDATION_ERROR', 'Channel ist kein WhatsApp-Channel.');
                }

                if ($channel->visibility === 'private' && (int) $channel->created_by_user_id !== (int) $context->user->id) {
                    if (!$this->isRootTeamAdmin($context, $rootTeam)) {
                        return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen privaten Kanal.');
                    }
                }
            }

            $query = CommsWhatsAppThread::query()
                ->where('team_id', $rootTeam->id)
                ->withCount('messages');

            // Filter by channel
            if ($channelId) {
                $query->where('comms_channel_id', $channelId);
            } else {
                // Only show threads from channels the user has access to
                $query->whereHas('channel', function ($q) use ($context, $rootTeam) {
                    $q->where('type', 'whatsapp')
                        ->where('is_active', true)
                        ->where(function ($q2) use ($context) {
                            $q2->where('visibility', 'team')
                                ->orWhere('created_by_user_id', $context->user->id);
                        });
                });
            }

            // Filter by unread status
            if ($isUnread !== null) {
                $query->where('is_unread', $isUnread);
            }

            // Filter by context
            if ($contextModel && $contextModelId) {
                $query->where('context_model', $contextModel)
                    ->where('context_model_id', $contextModelId);
            } elseif ($contextModel) {
                $query->where('context_model', $contextModel);
            }

            $this->applyStandardFilters($query, $arguments, [
                'remote_phone_number', 'is_unread', 'updated_at', 'created_at',
                'last_inbound_at', 'last_outbound_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['remote_phone_number', 'last_message_preview']);
            $this->applyStandardSort($query, $arguments, [
                'updated_at', 'created_at', 'last_inbound_at', 'last_outbound_at',
            ], 'updated_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);
            $threads = $result['data'];

            $items = $threads->map(function (CommsWhatsAppThread $t) {
                $messagesCount = (int) ($t->messages_count ?? 0);
                $lastIsInbound = $t->last_inbound_at && (!$t->last_outbound_at || $t->last_inbound_at->greaterThanOrEqualTo($t->last_outbound_at));
                $lastAt = $lastIsInbound ? $t->last_inbound_at : ($t->last_outbound_at ?: $t->updated_at);

                // Active conversation thread (pseudo-thread)
                $activeConvThread = CommsWhatsAppConversationThread::findActiveForThread($t->id);

                $item = [
                    'id' => (int) $t->id,
                    'comms_channel_id' => (int) $t->comms_channel_id,
                    'token' => (string) $t->token,
                    'remote_phone_number' => (string) $t->remote_phone_number,
                    'is_unread' => (bool) $t->is_unread,
                    'messages_count' => $messagesCount,
                    'last_direction' => $messagesCount > 0 ? ($lastIsInbound ? 'inbound' : 'outbound') : null,
                    'last_message_preview' => $t->last_message_preview,
                    'last_at' => $lastAt?->toIso8601String(),
                    'context_model' => $t->context_model,
                    'context_model_id' => $t->context_model_id ? (int) $t->context_model_id : null,
                    'updated_at' => $t->updated_at?->toIso8601String(),
                    'active_conversation_thread' => $activeConvThread ? [
                        'id' => (int) $activeConvThread->id,
                        'label' => $activeConvThread->label,
                        'started_at' => $activeConvThread->started_at?->toIso8601String(),
                    ] : null,
                ];

                return $item;
            })->values()->toArray();

            return ToolResult::success([
                'threads' => $items,
                'count' => count($items),
                'pagination' => $result['pagination'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der WhatsApp-Threads: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['comms', 'whatsapp', 'threads'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
