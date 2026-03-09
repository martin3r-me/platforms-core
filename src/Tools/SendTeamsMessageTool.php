<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\TeamsGraphService;
use Illuminate\Support\Facades\Log;

class SendTeamsMessageTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.comms.teams_messages.POST';
    }

    public function getDescription(): string
    {
        return 'Sends a message to Microsoft Teams — either to a channel, an existing chat, or as a new 1:1 DM. '
            . 'Requires the user to have authenticated via Azure SSO with Teams scopes. '
            . "\n\n"
            . 'TARGET TYPES: '
            . '"channel" — Send to a Teams channel (requires team_id + channel_id). '
            . '"chat" — Send to an existing chat thread (requires chat_id). '
            . '"dm" — Send a 1:1 direct message (requires recipient_email — creates chat automatically). '
            . "\n\n"
            . 'WORKFLOW: '
            . '1) Use core.comms.teams.GET to discover teams/channels/chats. '
            . '2) Use this tool to send the message. '
            . "\n\n"
            . 'CONTENT: message supports HTML (default) or plain text via content_type parameter.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'target_type' => [
                    'type' => 'string',
                    'enum' => ['channel', 'chat', 'dm'],
                    'description' => 'Where to send: "channel" (Teams channel), "chat" (existing chat), "dm" (new 1:1 DM by email).',
                ],
                'team_id' => [
                    'type' => 'string',
                    'description' => 'MS Teams team ID. Required for target_type=channel.',
                ],
                'channel_id' => [
                    'type' => 'string',
                    'description' => 'Channel ID. Required for target_type=channel.',
                ],
                'chat_id' => [
                    'type' => 'string',
                    'description' => 'Chat ID. Required for target_type=chat.',
                ],
                'recipient_email' => [
                    'type' => 'string',
                    'description' => 'Recipient email for 1:1 DM. Required for target_type=dm.',
                ],
                'message' => [
                    'type' => 'string',
                    'description' => 'Message content to send.',
                ],
                'content_type' => [
                    'type' => 'string',
                    'enum' => ['text', 'html'],
                    'description' => 'Content type: "html" (default) or "text".',
                ],
            ],
            'required' => ['target_type', 'message'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $targetType = $arguments['target_type'] ?? null;
        $message = $arguments['message'] ?? null;
        $contentType = $arguments['content_type'] ?? 'html';
        $user = $context->user;

        if (!$user) {
            return ToolResult::error('Kein User-Kontext verfügbar.', 'NO_USER');
        }

        if (!$message) {
            return ToolResult::error('message ist erforderlich.', 'VALIDATION_ERROR');
        }

        $service = app(TeamsGraphService::class);

        switch ($targetType) {
            case 'channel':
                return $this->sendToChannel($arguments, $user, $service, $message, $contentType, $context);

            case 'chat':
                return $this->sendToChat($arguments, $user, $service, $message, $contentType, $context);

            case 'dm':
                return $this->sendDm($arguments, $user, $service, $message, $contentType, $context);

            default:
                return ToolResult::error("Unbekannter target_type: {$targetType}. Erlaubt: channel, chat, dm.", 'VALIDATION_ERROR');
        }
    }

    private function sendToChannel(array $arguments, $user, TeamsGraphService $service, string $message, string $contentType, ToolContext $context): ToolResult
    {
        $teamId = $arguments['team_id'] ?? null;
        $channelId = $arguments['channel_id'] ?? null;

        if (!$teamId || !$channelId) {
            return ToolResult::error('team_id und channel_id sind erforderlich für target_type=channel.', 'VALIDATION_ERROR');
        }

        $result = $service->sendChannelMessage($user, $teamId, $channelId, $message, $contentType);

        if ($result === null) {
            return ToolResult::error(
                'Nachricht konnte nicht gesendet werden. Prüfe team_id/channel_id oder Teams-Scopes (ChannelMessage.Send).',
                'API_ERROR'
            );
        }

        Log::info('TeamsGraph: Channel message sent', [
            'user_id' => $user->id,
            'team_id' => $context->team?->id,
            'target' => "teams:{$teamId}:{$channelId}",
            'message_id' => $result['id'] ?? null,
        ]);

        return ToolResult::success([
            'sent' => true,
            'target_type' => 'channel',
            'message_id' => $result['id'] ?? null,
            'channel_id' => "teams:{$teamId}:{$channelId}",
            'created_at' => $result['createdDateTime'] ?? null,
        ]);
    }

    private function sendToChat(array $arguments, $user, TeamsGraphService $service, string $message, string $contentType, ToolContext $context): ToolResult
    {
        $chatId = $arguments['chat_id'] ?? null;

        if (!$chatId) {
            return ToolResult::error('chat_id ist erforderlich für target_type=chat.', 'VALIDATION_ERROR');
        }

        $result = $service->sendChatMessage($user, $chatId, $message, $contentType);

        if ($result === null) {
            return ToolResult::error(
                'Nachricht konnte nicht gesendet werden. Prüfe chat_id oder Teams-Scopes (ChatMessage.Send).',
                'API_ERROR'
            );
        }

        Log::info('TeamsGraph: Chat message sent', [
            'user_id' => $user->id,
            'team_id' => $context->team?->id,
            'target' => "teams:chat:{$chatId}",
            'message_id' => $result['id'] ?? null,
        ]);

        return ToolResult::success([
            'sent' => true,
            'target_type' => 'chat',
            'message_id' => $result['id'] ?? null,
            'channel_id' => "teams:chat:{$chatId}",
            'created_at' => $result['createdDateTime'] ?? null,
        ]);
    }

    private function sendDm(array $arguments, $user, TeamsGraphService $service, string $message, string $contentType, ToolContext $context): ToolResult
    {
        $recipientEmail = $arguments['recipient_email'] ?? null;

        if (!$recipientEmail) {
            return ToolResult::error('recipient_email ist erforderlich für target_type=dm.', 'VALIDATION_ERROR');
        }

        // 1. Create or get 1:1 chat
        $chat = $service->createOneOnOneChat($user, $recipientEmail);
        if ($chat === null) {
            return ToolResult::error(
                "Konnte 1:1 Chat mit {$recipientEmail} nicht erstellen. Prüfe ob der Empfänger im selben Tenant ist und Chat-Scopes vorhanden sind (Chat.ReadWrite).",
                'API_ERROR'
            );
        }

        $chatId = $chat['id'] ?? null;
        if (!$chatId) {
            return ToolResult::error('Chat wurde erstellt, aber keine Chat-ID erhalten.', 'API_ERROR');
        }

        // 2. Send message to the chat
        $result = $service->sendChatMessage($user, $chatId, $message, $contentType);

        if ($result === null) {
            return ToolResult::error(
                'Chat erstellt, aber Nachricht konnte nicht gesendet werden. Prüfe ChatMessage.Send Scope.',
                'API_ERROR'
            );
        }

        Log::info('TeamsGraph: DM sent', [
            'user_id' => $user->id,
            'team_id' => $context->team?->id,
            'target' => "teams:chat:{$chatId}",
            'recipient' => $recipientEmail,
            'message_id' => $result['id'] ?? null,
        ]);

        return ToolResult::success([
            'sent' => true,
            'target_type' => 'dm',
            'recipient_email' => $recipientEmail,
            'chat_id' => $chatId,
            'message_id' => $result['id'] ?? null,
            'channel_id' => "teams:chat:{$chatId}",
            'created_at' => $result['createdDateTime'] ?? null,
        ]);
    }
}
