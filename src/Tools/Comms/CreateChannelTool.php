<?php

namespace Platform\Core\Tools\Comms;

use Illuminate\Database\QueryException;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsProviderConnection;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class CreateChannelTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.channels.POST';
    }

    public function getDescription(): string
    {
        return 'POST /comms/channels – Erstellt einen E‑Mail Absender (Postmark) fürs Senden. Danach mit core.comms.email_messages.POST E‑Mails versenden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Es wird auf das Root-Team dieses Teams gespeichert.',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Kanaltyp. Aktuell: "email".',
                    'enum' => ['email'],
                ],
                'provider' => [
                    'type' => 'string',
                    'description' => 'Provider. Aktuell: "postmark".',
                    'enum' => ['postmark'],
                ],
                'sender_identifier' => [
                    'type' => 'string',
                    'description' => 'Absender-ID (ERFORDERLICH). Bei email/postmark: vollständige E-Mail Adresse.',
                ],
                'visibility' => [
                    'type' => 'string',
                    'enum' => ['private', 'team'],
                    'description' => 'Sichtbarkeit: private (nur Ersteller) oder team (teamweit). team nur für Owner/Admin des Root-Teams.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Anzeigename (z.B. "Support").',
                ],
            ],
            'required' => ['type', 'provider', 'sender_identifier', 'visibility'],
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

            $type = (string) ($arguments['type'] ?? '');
            $provider = (string) ($arguments['provider'] ?? '');
            $sender = trim((string) ($arguments['sender_identifier'] ?? ''));
            $visibility = (string) ($arguments['visibility'] ?? 'private');
            $name = trim((string) ($arguments['name'] ?? '')) ?: null;

            if ($sender === '' || strlen($sender) > 255) {
                return ToolResult::error('VALIDATION_ERROR', 'sender_identifier ist erforderlich (max. 255 Zeichen).');
            }

            if ($visibility === 'team' && !$this->isRootTeamAdmin($context, $rootTeam)) {
                return ToolResult::error('ACCESS_DENIED', 'Teamweite Kanäle dürfen nur Owner/Admin des Root-Teams anlegen.');
            }

            // Resolve provider connection (stored on root team)
            $connection = CommsProviderConnection::forTeamProvider($rootTeam, $provider);
            if (!$connection) {
                return ToolResult::error('MISSING_CONNECTION', 'Keine aktive Provider-Connection gefunden. Bitte zuerst in "Connections" konfigurieren.');
            }

            // Email/Postmark: enforce that sender domain exists in comms_provider_connection_domains (purpose=email)
            if ($type === 'email' && $provider === 'postmark') {
                if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
                    return ToolResult::error('VALIDATION_ERROR', 'sender_identifier muss eine gültige E‑Mail Adresse sein.');
                }

                $domain = strtolower((string) substr($sender, strrpos($sender, '@') + 1));
                $allowedDomains = $connection->domains()
                    ->where('purpose', 'email')
                    ->pluck('domain')
                    ->map(fn ($d) => strtolower((string) $d))
                    ->values()
                    ->all();

                if (empty($allowedDomains)) {
                    return ToolResult::error('MISSING_DOMAIN', 'Bitte zuerst mindestens eine Domain für Postmark hinterlegen (Connections → Domains).');
                }
                if (!in_array($domain, $allowedDomains, true)) {
                    return ToolResult::error('DOMAIN_NOT_ALLOWED', 'Absender-Domain ist nicht in den Postmark-Domains hinterlegt.');
                }
            }

            try {
                $channel = CommsChannel::create([
                    'team_id' => $rootTeam->id,
                    'created_by_user_id' => $context->user->id,
                    'comms_provider_connection_id' => $connection->id,
                    'type' => $type,
                    'provider' => $provider,
                    'name' => $name,
                    'sender_identifier' => $sender,
                    'visibility' => $visibility,
                    'is_active' => true,
                    'meta' => [],
                ]);
            } catch (QueryException $e) {
                return ToolResult::error('ALREADY_EXISTS', 'Dieser Kanal existiert bereits (Team/Typ/Absender).');
            }

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
                'message' => 'Kanal angelegt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Kanals: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['comms', 'channels', 'create', 'email', 'postmark'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}

