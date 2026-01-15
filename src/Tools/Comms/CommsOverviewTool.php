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
        return 'GET /comms/overview – Schnellstart für E‑Mail Versand: channels.GET -> email_messages.POST (send) -> email_messages.GET (timeline). Root-Team Scope + Postmark.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'note' => [
                    'type' => 'string',
                    'description' => 'Optional: Freitext, wofür der Überblick benötigt wird (wird nur zurückgespiegelt).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::success([
            'note' => $arguments['note'] ?? null,
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
                    'steps' => [
                        '1) core.comms.channels.GET (type=email, provider=postmark) -> comms_channel_id wählen',
                        '2) core.comms.email_messages.POST { comms_channel_id, to, subject, body }',
                        '3) core.comms.email_messages.GET { thread_id } (optional)',
                    ],
                ],
                [
                    'name' => 'E‑Mail senden (reply)',
                    'steps' => [
                        '1) core.comms.email_threads.GET { comms_channel_id } -> thread_id wählen',
                        '2) core.comms.email_messages.POST { thread_id, body } (to/subject werden aus Thread-Rollups abgeleitet)',
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

