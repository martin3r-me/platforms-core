<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class ListChannelsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.channels.GET';
    }

    public function getDescription(): string
    {
        return 'GET /comms/channels – Liste verfügbarer E‑Mail Absender (Postmark). Nutze die comms_channel_id aus dem Ergebnis für core.comms.email_messages.POST. Filter: type=email, provider=postmark.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Wenn angegeben, wird auf das Root-Team dieses Teams aufgelöst. Standard: aktuelles Team.',
                    ],
                    'type' => [
                        'type' => 'string',
                        'description' => 'Optional: Kanaltyp (z.B. "email").',
                    ],
                    'provider' => [
                        'type' => 'string',
                        'description' => 'Optional: Provider (z.B. "postmark").',
                    ],
                    'visibility' => [
                        'type' => 'string',
                        'enum' => ['private', 'team'],
                        'description' => 'Optional: Sichtbarkeit filtern (private|team).',
                    ],
                    'include_inactive' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Inaktive Kanäle einbeziehen. Standard: false.',
                    ],
                    'include_all' => [
                        'type' => 'boolean',
                        'description' => 'Optional: (nur Owner/Admin des Root-Teams) auch private Kanäle anderer User listen. Standard: false.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveRootTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }

            $rootTeam = $resolved['team'];
            $includeInactive = (bool) ($arguments['include_inactive'] ?? false);
            $includeAll = (bool) ($arguments['include_all'] ?? false);
            $isAdmin = $this->isRootTeamAdmin($context, $rootTeam);

            $query = CommsChannel::query()
                ->where('team_id', $rootTeam->id);

            if (!empty($arguments['type'])) {
                $query->where('type', (string) $arguments['type']);
            }
            if (!empty($arguments['provider'])) {
                $query->where('provider', (string) $arguments['provider']);
            }
            if (!empty($arguments['visibility'])) {
                $query->where('visibility', (string) $arguments['visibility']);
            }
            if (!$includeInactive) {
                $query->where('is_active', true);
            }

            // Default: only team channels + private channels created by current user.
            if (!($includeAll && $isAdmin)) {
                $query->where(function ($q) use ($context) {
                    $q->where('visibility', 'team')
                        ->orWhere(function ($q2) use ($context) {
                            $q2->where('visibility', 'private')->where('created_by_user_id', $context->user->id);
                        });
                });
            }

            $this->applyStandardFilters($query, $arguments, [
                'type', 'provider', 'visibility', 'is_active', 'sender_identifier', 'name', 'created_by_user_id', 'created_at', 'updated_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['sender_identifier', 'name']);
            $this->applyStandardSort($query, $arguments, ['sender_identifier', 'visibility', 'type', 'provider', 'created_at', 'updated_at'], 'sender_identifier', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);
            $channels = $result['data'];

            $items = $channels->map(fn (CommsChannel $c) => [
                'id' => (int) $c->id,
                'team_id' => (int) $c->team_id,
                'type' => (string) $c->type,
                'provider' => (string) $c->provider,
                'name' => $c->name ? (string) $c->name : null,
                'sender_identifier' => (string) $c->sender_identifier,
                'visibility' => (string) $c->visibility,
                'is_active' => (bool) $c->is_active,
                'created_by_user_id' => (int) $c->created_by_user_id,
                'created_at' => $c->created_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'channels' => $items,
                'count' => count($items),
                'pagination' => $result['pagination'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Channels: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['comms', 'channels', 'email'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}

