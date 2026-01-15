<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class ListEmailThreadsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.email_threads.GET';
    }

    public function getDescription(): string
    {
        return 'GET /comms/email_threads – Threads zu einem Absender (comms_channel_id). Für Reply: thread_id wählen und mit core.comms.email_messages.POST senden.';
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
                        'description' => 'ERFORDERLICH: Channel-ID (comms_channels.id).',
                    ],
                ],
                'required' => ['comms_channel_id'],
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

            // Access like UI runtime:
            // - team channels: visible to all team members
            // - private channels: only creator (and admins may manage, but not "use" by default)
            if ($channel->visibility === 'private' && (int) $channel->created_by_user_id !== (int) $context->user->id) {
                if (!$this->isRootTeamAdmin($context, $rootTeam)) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen privaten Kanal.');
                }
            }

            $query = CommsEmailThread::query()
                ->where('team_id', $rootTeam->id)
                ->where('comms_channel_id', $channelId)
                ->withCount(['inboundMails', 'outboundMails']);

            $this->applyStandardFilters($query, $arguments, ['subject', 'token', 'updated_at', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['subject', 'token']);
            $this->applyStandardSort($query, $arguments, ['updated_at', 'created_at', 'subject'], 'updated_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);
            $threads = $result['data'];

            $items = $threads->map(function (CommsEmailThread $t) {
                $messagesCount = (int) (($t->inbound_mails_count ?? 0) + ($t->outbound_mails_count ?? 0));
                $lastIsInbound = $t->last_inbound_at && (!$t->last_outbound_at || $t->last_inbound_at->greaterThanOrEqualTo($t->last_outbound_at));
                $lastAt = $lastIsInbound ? $t->last_inbound_at : ($t->last_outbound_at ?: $t->updated_at);

                return [
                    'id' => (int) $t->id,
                    'comms_channel_id' => (int) $t->comms_channel_id,
                    'token' => (string) $t->token,
                    'subject' => (string) ($t->subject ?: 'Ohne Betreff'),
                    'counterpart' => (string) ($t->last_inbound_from_address ?: $t->last_outbound_to_address ?: ''),
                    'messages_count' => $messagesCount,
                    'last_direction' => $messagesCount > 0 ? ($lastIsInbound ? 'inbound' : 'outbound') : null,
                    'last_at' => $lastAt?->toIso8601String(),
                    'updated_at' => $t->updated_at?->toIso8601String(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'threads' => $items,
                'count' => count($items),
                'pagination' => $result['pagination'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Threads: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['comms', 'email', 'threads'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

