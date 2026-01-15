<?php

namespace Platform\Core\Tools\Communication;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Pseudo module entrypoint for tool discovery.
 *
 * Why: tools.GET requires a module prefix. Many "business" modules exist,
 * and comms tools live under core.comms.*. This tool makes "communication"
 * discoverable without duplicating the real tools.
 */
class CommunicationOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'communication.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /communication/overview – Einstieg für E‑Mail Versand. Workflow: 1) core.comms.channels.GET (type=email) → 2) core.comms.email_messages.POST (send) → 3) core.comms.email_messages.GET (timeline). Für Details: core.comms.overview.GET.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'goal' => [
                    'type' => 'string',
                    'description' => 'Optional: Ziel/Use-Case (z.B. "Sende eine E‑Mail", "Liste Threads").',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::success([
            'goal' => $arguments['goal'] ?? null,
            'hint' => 'Die echten Comms-Tools liegen unter core.comms.* (Root-Team Scope, Postmark Credentials in DB).',
            'start_here' => [
                'overview' => 'core.comms.overview.GET',
            ],
            'email_send_flow' => [
                '1_list_channels' => 'core.comms.channels.GET',
                '2_send_email' => 'core.comms.email_messages.POST',
                '3_read_timeline' => 'core.comms.email_messages.GET',
                'optional_list_threads' => 'core.comms.email_threads.GET',
            ],
            'tools' => [
                'channels' => [
                    'core.comms.channels.GET',
                    'core.comms.channels.POST',
                    'core.comms.channels.PUT',
                    'core.comms.channels.DELETE',
                ],
                'threads' => [
                    'core.comms.email_threads.GET',
                    'core.comms.email_threads.POST',
                    'core.comms.email_threads.PUT',
                    'core.comms.email_threads.DELETE',
                ],
                'messages' => [
                    'core.comms.email_messages.GET',
                    'core.comms.email_messages.POST',
                ],
            ],
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'utility',
            'tags' => ['communication', 'comms', 'email', 'send', 'postmark', 'overview'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

