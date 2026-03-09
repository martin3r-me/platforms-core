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
        return 'Lists Microsoft Teams resources: joined teams, channels, chats, and message history. '
            . 'Requires the user to have authenticated via Azure SSO with Teams scopes. '
            . "\n\n"
            . 'ACTIONS: '
            . '"teams" — List all Teams the user has joined. Returns id, displayName, description. '
            . '"channels" — List channels of a specific team (requires team_id). Returns id, displayName, description, membershipType. '
            . '"chats" — List recent DM/group chats with member names. Returns id, topic, chatType, members, lastMessagePreview. '
            . '"messages" — Read recent messages from a chat or channel. Requires chat_id OR (team_id + channel_id). Returns sender, content, timestamp.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['teams', 'channels', 'chats', 'messages'],
                    'description' => 'What to list: "teams", "channels" (requires team_id), "chats", "messages" (requires chat_id or team_id+channel_id).',
                ],
                'team_id' => [
                    'type' => 'string',
                    'description' => 'MS Teams team ID. Required for action=channels. For action=messages with channel, provide together with channel_id.',
                ],
                'channel_id' => [
                    'type' => 'string',
                    'description' => 'Channel ID. For action=messages from a channel (together with team_id).',
                ],
                'chat_id' => [
                    'type' => 'string',
                    'description' => 'Chat ID. For action=messages from a DM/group chat.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max results (default 25 for chats, 20 for messages).',
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
                return $this->listTeams($service, $user);

            case 'channels':
                return $this->listChannels($service, $user, $arguments);

            case 'chats':
                return $this->listChats($service, $user, $arguments);

            case 'messages':
                return $this->listMessages($service, $user, $arguments);

            default:
                return ToolResult::error("Unbekannte action: {$action}. Erlaubt: teams, channels, chats, messages.", 'VALIDATION_ERROR');
        }
    }

    private function listTeams(TeamsGraphService $service, $user): ToolResult
    {
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
    }

    private function listChannels(TeamsGraphService $service, $user, array $arguments): ToolResult
    {
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
    }

    private function listChats(TeamsGraphService $service, $user, array $arguments): ToolResult
    {
        $limit = $arguments['limit'] ?? 25;
        $chats = $service->getRecentChats($user, $limit);
        if ($chats === null) {
            return ToolResult::error(
                'Konnte Chats nicht abrufen. Möglicherweise fehlen Chat-Scopes — User muss sich erneut über Azure SSO einloggen.',
                'API_ERROR'
            );
        }

        $result = array_map(function ($c) {
            $members = [];
            foreach ($c['members'] ?? [] as $m) {
                $members[] = [
                    'displayName' => $m['displayName'] ?? null,
                    'email' => $m['email'] ?? null,
                ];
            }

            return [
                'id' => $c['id'] ?? null,
                'topic' => $c['topic'] ?? null,
                'chatType' => $c['chatType'] ?? null,
                'members' => $members,
                'lastMessagePreview' => isset($c['lastMessagePreview']) ? [
                    'body' => $c['lastMessagePreview']['body']['content'] ?? null,
                    'from' => $c['lastMessagePreview']['from']['user']['displayName'] ?? null,
                    'createdDateTime' => $c['lastMessagePreview']['createdDateTime'] ?? null,
                ] : null,
            ];
        }, $chats);

        return ToolResult::success([
            'action' => 'chats',
            'count' => count($result),
            'chats' => $result,
        ]);
    }

    private function listMessages(TeamsGraphService $service, $user, array $arguments): ToolResult
    {
        $chatId = $arguments['chat_id'] ?? null;
        $teamId = $arguments['team_id'] ?? null;
        $channelId = $arguments['channel_id'] ?? null;
        $limit = $arguments['limit'] ?? 20;

        if ($chatId) {
            // Chat messages (DM / group chat)
            $messages = $service->getChatMessages($user, $chatId, $limit);
            if ($messages === null) {
                return ToolResult::error(
                    'Konnte Chat-Nachrichten nicht abrufen. Prüfe chat_id oder ChatMessage.Read Scope.',
                    'API_ERROR'
                );
            }

            return ToolResult::success([
                'action' => 'messages',
                'source' => 'chat',
                'chat_id' => $chatId,
                'count' => count($messages),
                'messages' => $this->formatMessages($messages),
            ]);
        }

        if ($teamId && $channelId) {
            // Channel messages
            $messages = $service->getChannelMessages($user, $teamId, $channelId, $limit);
            if ($messages === null) {
                return ToolResult::error(
                    'Konnte Channel-Nachrichten nicht abrufen. Prüfe team_id/channel_id oder ChannelMessage.Read.All Scope.',
                    'API_ERROR'
                );
            }

            return ToolResult::success([
                'action' => 'messages',
                'source' => 'channel',
                'team_id' => $teamId,
                'channel_id' => $channelId,
                'count' => count($messages),
                'messages' => $this->formatMessages($messages),
            ]);
        }

        return ToolResult::error(
            'Für action=messages: entweder chat_id ODER team_id+channel_id angeben.',
            'VALIDATION_ERROR'
        );
    }

    private function formatMessages(array $messages): array
    {
        return array_map(function ($m) {
            // Skip system/event messages without body content
            $bodyContent = $m['body']['content'] ?? null;
            $messageType = $m['messageType'] ?? 'message';

            return [
                'id' => $m['id'] ?? null,
                'messageType' => $messageType,
                'from' => $m['from']['user']['displayName'] ?? $m['from']['application']['displayName'] ?? null,
                'contentType' => $m['body']['contentType'] ?? null,
                'content' => $bodyContent,
                'createdDateTime' => $m['createdDateTime'] ?? null,
                'attachments' => !empty($m['attachments']) ? array_map(fn($a) => [
                    'id' => $a['id'] ?? null,
                    'contentType' => $a['contentType'] ?? null,
                    'name' => $a['name'] ?? null,
                ], $m['attachments']) : null,
            ];
        }, $messages);
    }
}
