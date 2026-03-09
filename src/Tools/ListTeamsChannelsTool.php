<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\TeamsGraphService;

class ListTeamsChannelsTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.comms.teams.GET';
    }

    public function getDescription(): string
    {
        return 'Lists Microsoft Teams resources: joined teams, channels within a team, or recent DM chats. '
            . 'Requires the user to have authenticated via Azure SSO with Teams scopes. '
            . "\n\n"
            . 'ACTIONS: '
            . '"teams" — List all Teams the user has joined. Returns id, displayName, description. '
            . '"channels" — List channels of a specific team (requires team_id). Returns id, displayName, description, membershipType. '
            . '"chats" — List recent DM/group chats. Returns id, topic, chatType, lastMessagePreview.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['teams', 'channels', 'chats'],
                    'description' => 'What to list: "teams" (joined teams), "channels" (channels of a team), "chats" (recent DM chats).',
                ],
                'team_id' => [
                    'type' => 'string',
                    'description' => 'MS Teams team ID. Required when action=channels.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max results for chats (default 25).',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $action = $arguments['action'] ?? null;
        $user = $context->user;

        if (!$user) {
            return ToolResult::error('Kein User-Kontext verfügbar.', 'NO_USER');
        }

        $service = app(TeamsGraphService::class);

        switch ($action) {
            case 'teams':
                $teams = $service->getJoinedTeams($user);
                if ($teams === null) {
                    return ToolResult::error(
                        'Konnte Teams nicht abrufen. Möglicherweise fehlen Teams-Scopes — User muss sich erneut über Azure SSO einloggen.',
                        'API_ERROR'
                    );
                }

                $result = array_map(fn($t) => [
                    'id' => $t['id'] ?? null,
                    'displayName' => $t['displayName'] ?? null,
                    'description' => $t['description'] ?? null,
                ], $teams);

                return ToolResult::success([
                    'action' => 'teams',
                    'count' => count($result),
                    'teams' => $result,
                ]);

            case 'channels':
                $teamId = $arguments['team_id'] ?? null;
                if (!$teamId) {
                    return ToolResult::error('team_id ist erforderlich für action=channels.', 'VALIDATION_ERROR');
                }

                $channels = $service->getChannels($user, $teamId);
                if ($channels === null) {
                    return ToolResult::error(
                        'Konnte Channels nicht abrufen. Prüfe team_id oder Teams-Scopes.',
                        'API_ERROR'
                    );
                }

                $result = array_map(fn($c) => [
                    'id' => $c['id'] ?? null,
                    'displayName' => $c['displayName'] ?? null,
                    'description' => $c['description'] ?? null,
                    'membershipType' => $c['membershipType'] ?? null,
                ], $channels);

                return ToolResult::success([
                    'action' => 'channels',
                    'team_id' => $teamId,
                    'count' => count($result),
                    'channels' => $result,
                ]);

            case 'chats':
                $limit = $arguments['limit'] ?? 25;
                $chats = $service->getRecentChats($user, $limit);
                if ($chats === null) {
                    return ToolResult::error(
                        'Konnte Chats nicht abrufen. Möglicherweise fehlen Chat-Scopes — User muss sich erneut über Azure SSO einloggen.',
                        'API_ERROR'
                    );
                }

                $result = array_map(fn($c) => [
                    'id' => $c['id'] ?? null,
                    'topic' => $c['topic'] ?? null,
                    'chatType' => $c['chatType'] ?? null,
                    'lastMessagePreview' => isset($c['lastMessagePreview']) ? [
                        'body' => $c['lastMessagePreview']['body']['content'] ?? null,
                        'createdDateTime' => $c['lastMessagePreview']['createdDateTime'] ?? null,
                    ] : null,
                ], $chats);

                return ToolResult::success([
                    'action' => 'chats',
                    'count' => count($result),
                    'chats' => $result,
                ]);

            default:
                return ToolResult::error("Unbekannte action: {$action}. Erlaubt: teams, channels, chats.", 'VALIDATION_ERROR');
        }
    }
}
