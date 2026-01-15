<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * High-signal discovery entrypoint for Comms.
 *
 * Goal: make it obvious to an LLM how to send emails:
 * Connection -> Channel -> Thread -> Send -> Read timeline.
 */
class CommsOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.comms.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /comms/overview – Übersicht über Communication-Tools und Workflows. Zeigt verfügbare Tools (channels, threads, messages) und typische Abläufe für E‑Mail Versand. REST-Parameter: keine.';
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
        return ToolResult::success([
            'capabilities' => [
                'channels' => [
                    'purpose' => 'Sender-IDs (z.B. E‑Mail Absender) am Root-Team, inkl. Sichtbarkeit (private/team).',
                    'tools' => [
                        'list' => 'core.comms.channels.GET',
                        'create' => 'core.comms.channels.POST',
                        'update' => 'core.comms.channels.PUT',
                        'delete' => 'core.comms.channels.DELETE',
                    ],
                ],
                'email_threads' => [
                    'purpose' => 'Konversationen pro Kanal (thread token + subject + rollups).',
                    'tools' => [
                        'list' => 'core.comms.email_threads.GET',
                        'create' => 'core.comms.email_threads.POST',
                        'update' => 'core.comms.email_threads.PUT',
                        'delete' => 'core.comms.email_threads.DELETE',
                    ],
                ],
                'email_messages' => [
                    'purpose' => 'Timeline lesen + E‑Mails senden (Postmark).',
                    'tools' => [
                        'list' => 'core.comms.email_messages.GET',
                        'send' => 'core.comms.email_messages.POST',
                    ],
                ],
            ],
            'typical_flows' => [
                [
                    'name' => 'E‑Mail senden (neuer Thread)',
                    'description' => 'Schritt-für-Schritt:',
                    'steps' => [
                        '1) core.comms.channels.GET { type: "email", provider: "postmark" } → wähle eine comms_channel_id aus dem Ergebnis',
                        '2) core.comms.email_messages.POST { comms_channel_id: <id>, to: "empfaenger@example.com", subject: "Betreff", body: "Nachrichtentext" }',
                        '3) Optional: core.comms.email_messages.GET { thread_id: <id> } um die Timeline zu sehen',
                    ],
                    'example' => [
                        'channels_get' => 'core.comms.channels.GET({ type: "email", provider: "postmark" })',
                        'send_post' => 'core.comms.email_messages.POST({ comms_channel_id: 1, to: "test@example.com", subject: "Test", body: "Hallo" })',
                    ],
                ],
                [
                    'name' => 'E‑Mail senden (Reply)',
                    'description' => 'Antwort auf bestehenden Thread:',
                    'steps' => [
                        '1) core.comms.email_threads.GET { comms_channel_id: <id> } → wähle eine thread_id aus',
                        '2) core.comms.email_messages.POST { thread_id: <id>, body: "Antworttext" }',
                        'Hinweis: to und subject werden automatisch aus dem Thread übernommen (keine Angabe nötig)',
                    ],
                    'example' => [
                        'threads_get' => 'core.comms.email_threads.GET({ comms_channel_id: 1 })',
                        'reply_post' => 'core.comms.email_messages.POST({ thread_id: 5, body: "Meine Antwort" })',
                    ],
                ],
            ],
            'notes' => [
                'Comms-Daten sind root-scoped (Root-Team).',
                'Postmark Credentials liegen in der DB (Provider Connection).',
                'Teamweite Kanäle/Threads löschen: nur Owner/Admin (Root-Team).',
            ],
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'utility',
            'tags' => ['comms', 'overview', 'email', 'send', 'postmark'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

