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
 * Microsoft-Teams-Chats/-Channels und Mail werden bewusst NICHT live abgefragt: der
 * zugrunde liegende Microsoft365-Connector liefert für diese drei strukturell keinen
 * Unread-Wert (Graph-API-Limitierung bzw. fehlender $count-Request), ein Live-Call würde
 * dort nur unnötige externe API-Kosten verursachen, ohne je einen Wert zu liefern.
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
            . 'aus terminal.channels.GET (internes Messaging) und core.comms.wa_overview.GET (WhatsApp). '
            . 'Für Kanäle, deren API strukturell keinen Unread-Wert liefert (Microsoft-Teams-Chats/-Channels, Mail) '
            . 'wird unread_count ehrlich null mit Begründung geliefert statt geraten oder verschwiegen.';
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

        $terminal = $executor->execute('terminal.channels.GET', [], $context);
        if ($terminal->success) {
            $unread = array_sum(array_column($terminal->data['channels'] ?? [], 'unread'));
            $channels[] = [
                'channel' => 'terminal',
                'unread_count' => $unread,
                'source_tool' => 'terminal.channels.GET',
            ];
            $totalUnread += $unread;
        } else {
            $channels[] = [
                'channel' => 'terminal',
                'unread_count' => null,
                'source_tool' => 'terminal.channels.GET',
                'reason' => $terminal->error ?? 'Quelle nicht verfügbar.',
            ];
        }

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

        $channels[] = [
            'channel' => 'teams-chat',
            'unread_count' => null,
            'source_tool' => 'user-connectors.microsoft365.teams.chats.list',
            'reason' => 'Microsoft Graph liefert für Teams-Chats über diesen Connector keinen Unread-Status.',
        ];

        $channels[] = [
            'channel' => 'teams-channel',
            'unread_count' => null,
            'source_tool' => 'user-connectors.microsoft365.teams.channels.list',
            'reason' => 'Microsoft Graph liefert für Teams-Channels keinen Unread-Status (nur last_message_at als Aktivitäts-Proxy).',
        ];

        $channels[] = [
            'channel' => 'mail',
            'unread_count' => null,
            'source_tool' => 'user-connectors.microsoft365.mail.list',
            'reason' => 'Connector fragt aktuell kein Graph-$count an (nur is_read-Filter pro Nachricht) — Gesamtzahl ungelesener Mails ist ohne separate Connector-Erweiterung nicht verlässlich ermittelbar.',
        ];

        return ToolResult::success([
            'channels' => $channels,
            'total_unread' => $totalUnread,
            'total_unread_note' => 'Summe nur über Kanäle mit tatsächlichem (nicht-null) unread_count.',
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => [
                'action:unread',
                'action:list',
                'channel:terminal',
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
            'cost_class' => 'local_db',
            'related_tools' => [
                'terminal.channels.GET',
                'core.comms.wa_overview.GET',
                'user-connectors.microsoft365.teams.chats.list',
                'user-connectors.microsoft365.teams.channels.list',
                'user-connectors.microsoft365.mail.list',
            ],
        ];
    }
}
