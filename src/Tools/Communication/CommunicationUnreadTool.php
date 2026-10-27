<?php

namespace Platform\Core\Tools\Communication;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\ToolExecutor;

/**
 * Dünner Orchestrator: aggregiert Unread-Zähler kanalübergreifend über bestehende Tools.
 *
 * Baut absichtlich KEIN eigenes Auth/Caching auf — jede Unter-Quelle läuft durch
 * ToolExecutor::execute() und damit durch deren eigene Permission-/Cache-Logik.
 *
 * Mail und Teams-Chats kommen live über den Microsoft365-Connector: mail.list liefert per
 * Graph-$count die Zahl ungelesener Posteingangs-Mails, teams.chats.list markiert Chats als
 * unread (lastMessagePreview.createdDateTime > viewpoint.lastMessageReadDateTime, laut
 * Graph-Doku chat-list). Teams-Channels kennen in Graph keinen Lesestatus → bleibt null.
 */
class CommunicationUnreadTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'communication.unread.GET';
    }

    public function getDescription(): string
    {
        return 'GET /communication/unread - Kanalübergreifende Unread-Übersicht. Aggregiert echte Ungelesen-Zähler '
            . 'aus WhatsApp, Outlook-Posteingang (Anzahl ungelesener Mails) und Teams-Chats (Anzahl Chats mit ungelesenen Nachrichten). '
            . 'Teams-Channels liefern strukturell keinen Unread-Wert — dort und bei fehlender Verbindung ist unread_count '
            . 'null mit Begründung statt geraten.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $executor = app(ToolExecutor::class);
        $channels = [];
        $totalUnread = 0;

        $whatsapp = $executor->execute('core.comms.wa_overview.GET', [], $context);
        if ($whatsapp->success) {
            $unread = $whatsapp->data['summary']['unread_contacts'] ?? null;
            $channels[] = [
                'channel' => 'whatsapp',
                'unread_count' => $unread,
                'source_tool' => 'core.comms.wa_overview.GET',
            ];
            if (is_int($unread)) {
                $totalUnread += $unread;
            }
        } else {
            $channels[] = [
                'channel' => 'whatsapp',
                'unread_count' => null,
                'source_tool' => 'core.comms.wa_overview.GET',
                'reason' => $whatsapp->error ?? 'Quelle nicht verfügbar (z. B. kein WhatsApp-Channel im Team).',
            ];
        }

        // Teams-Chats: unread je Chat (sortiert nach letzter Aktivität, max. 50 jüngste Chats).
        $chats = $executor->execute('user-connectors.microsoft365.teams.chats.list', [
            'unread_only' => true,
            'include_members' => false,
            'limit' => 50,
        ], $context);
        if ($chats->success) {
            $unreadChats = count($chats->data['chats'] ?? []);
            $channels[] = array_filter([
                'channel' => 'teams-chat',
                'unread_count' => $unreadChats,
                'unit' => 'chats',
                'source_tool' => 'user-connectors.microsoft365.teams.chats.list',
                // Nur die 50 zuletzt aktiven Chats geprüft — ältere ungelesene sind selten, aber möglich.
                'note' => !empty($chats->data['next_cursor']) ? 'Geprüft: die 50 zuletzt aktiven Chats.' : null,
                'reason' => $chats->data['sort_warning'] ?? null,
            ], fn ($v) => $v !== null);
            $totalUnread += $unreadChats;
        } else {
            $channels[] = [
                'channel' => 'teams-chat',
                'unread_count' => null,
                'source_tool' => 'user-connectors.microsoft365.teams.chats.list',
                'reason' => $chats->error ?? 'Quelle nicht verfügbar (z. B. keine Microsoft-365-Verbindung).',
            ];
        }

        $channels[] = [
            'channel' => 'teams-channel',
            'unread_count' => null,
            'source_tool' => 'user-connectors.microsoft365.teams.channels.list',
            'reason' => 'Microsoft Graph kennt für Teams-Channels keinen Lesestatus (nur last_message_at per include_last_message als Aktivitäts-Proxy).',
        ];

        // Mail: Graph-$count ungelesener Mails im Posteingang (eine Mail Payload, body_format none).
        $mail = $executor->execute('user-connectors.microsoft365.mail.list', [
            'folder' => 'inbox',
            'is_read' => false,
            'per_page' => 1,
            'body_format' => 'none',
        ], $context);
        $mailCount = $mail->success ? ($mail->data['total'] ?? null) : null;
        if (is_int($mailCount)) {
            $channels[] = [
                'channel' => 'mail',
                'unread_count' => $mailCount,
                'unit' => 'messages',
                'scope' => 'inbox',
                'source_tool' => 'user-connectors.microsoft365.mail.list',
            ];
            $totalUnread += $mailCount;
        } else {
            $channels[] = [
                'channel' => 'mail',
                'unread_count' => null,
                'source_tool' => 'user-connectors.microsoft365.mail.list',
                'reason' => $mail->success
                    ? 'Graph lieferte keine Gesamtzahl.'
                    : ($mail->error ?? 'Quelle nicht verfügbar (z. B. keine Microsoft-365-Verbindung).'),
            ];
        }

        return ToolResult::success([
            'channels' => $channels,
            'total_unread' => $totalUnread,
            'total_unread_note' => 'Summe nur über Kanäle mit tatsächlichem (nicht-null) unread_count; Einheiten gemischt (WhatsApp-Kontakte, Teams-Chats, Mails).',
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => [
                'action:unread',
                'action:list',
                'channel:whatsapp',
                'channel:teams-chat',
                'channel:teams-channel',
                'channel:mail',
            ],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
            'cost_class' => 'external_api_free',
            'related_tools' => [
                'core.comms.wa_overview.GET',
                'user-connectors.microsoft365.teams.chats.list',
                'user-connectors.microsoft365.teams.channels.list',
                'user-connectors.microsoft365.mail.list',
            ],
        ];
    }
}
