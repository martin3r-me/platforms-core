<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class UpdateChannelTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.channels.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /comms/channels/{id} - Aktualisiert Channel-Felder (name, visibility, is_active) im Root-Team Scope. sender_identifier/type/provider sind bewusst nicht änderbar.';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Anzeigename. Leerer String setzt auf null.',
                ],
                'visibility' => [
                    'type' => 'string',
                    'enum' => ['private', 'team'],
                    'description' => 'Optional: Sichtbarkeit. team nur für Owner/Admin des Root-Teams.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Aktiv/Inaktiv.',
                ],
            ],
            'required' => ['channel_id'],
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

            // Permissions like UI:
            // - team channels: only owner/admin can update
            // - private channels: owner/admin or creator
            if ($channel->visibility === 'team') {
                if (!$isAdmin) {
                    return ToolResult::error('ACCESS_DENIED', 'Teamweite Kanäle können nur Owner/Admin bearbeiten.');
                }
            } else {
                if (!$isAdmin && !$isCreator) {
                    return ToolResult::error('ACCESS_DENIED', 'Privater Kanal gehört einem anderen User.');
                }
            }

            if (array_key_exists('visibility', $arguments)) {
                $newVisibility = (string) ($arguments['visibility'] ?? '');
                if (!in_array($newVisibility, ['private', 'team'], true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'visibility muss "private" oder "team" sein.');
                }
                if ($newVisibility === 'team' && !$isAdmin) {
                    return ToolResult::error('ACCESS_DENIED', 'Teamweite Kanäle dürfen nur Owner/Admin setzen.');
                }
                $channel->visibility = $newVisibility;
            }

            if (array_key_exists('name', $arguments)) {
                $name = trim((string) ($arguments['name'] ?? ''));
                $channel->name = $name !== '' ? $name : null;
            }

            if (array_key_exists('is_active', $arguments)) {
                $channel->is_active = (bool) $arguments['is_active'];
            }

            $channel->save();

            return ToolResult::success([
                'channel' => [
                    'id' => (int) $channel->id,
                    'team_id' => (int) $channel->team_id,
                    'type' => (string) $channel->type,
                    'provider' => (string) $channel->provider,
                    'name' => $channel->name ? (string) $channel->name : null,
                    'sender_identifier' => (string) $channel->sender_identifier,
                    'visibility' => (string) $channel->visibility,
                    'is_active' => (bool) $channel->is_active,
                    'created_by_user_id' => (int) $channel->created_by_user_id,
                ],
                'message' => 'Channel aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Channels: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['comms', 'channels', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}

