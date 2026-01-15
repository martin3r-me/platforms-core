<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class DeleteChannelTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.channels.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /comms/channels/{id} - Löscht einen Channel (soft delete). Parameter: channel_id (required), confirm=true (required), team_id (optional).';
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
                'channel_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Channels.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true, um wirklich zu löschen.',
                ],
            ],
            'required' => ['channel_id', 'confirm'],
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

            $channelId = (int) ($arguments['channel_id'] ?? 0);
            if ($channelId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'channel_id ist erforderlich.');
            }

            $channel = CommsChannel::query()
                ->where('team_id', $rootTeam->id)
                ->whereKey($channelId)
                ->first();

            if (!$channel) {
                return ToolResult::error('NOT_FOUND', 'Channel nicht gefunden.');
            }

            $isAdmin = $this->isRootTeamAdmin($context, $rootTeam);
            $isCreator = (int) $channel->created_by_user_id === (int) $context->user->id;

            // Permission (same as UI):
            // - team channels: only owner/admin
            // - private channels: owner/admin or creator
            if ($channel->visibility === 'team') {
                if (!$isAdmin) {
                    return ToolResult::error('ACCESS_DENIED', 'Teamweite Kanäle dürfen nur Owner/Admin löschen.');
                }
            } else {
                if (!$isAdmin && !$isCreator) {
                    return ToolResult::error('ACCESS_DENIED', 'Privater Kanal gehört einem anderen User.');
                }
            }

            $id = (int) $channel->id;
            $sender = (string) $channel->sender_identifier;
            $channel->delete();

            return ToolResult::success([
                'channel_id' => $id,
                'sender_identifier' => $sender,
                'message' => 'Channel gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Channels: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['comms', 'channels', 'delete'],
            'risk_level' => 'destructive',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
            'confirmation_required' => true,
        ];
    }
}

