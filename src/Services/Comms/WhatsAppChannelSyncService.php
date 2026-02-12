<?php

namespace Platform\Core\Services\Comms;

use Platform\Core\Models\Team;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsProviderConnection;
use Platform\Integrations\Models\IntegrationsWhatsAppAccount;
use Illuminate\Support\Facades\Log;

/**
 * Synchronisiert WhatsApp-Accounts aus der Integration zu CommsChannels
 */
class WhatsAppChannelSyncService
{
    /**
     * Sync alle WhatsApp-Accounts eines Teams zu CommsChannels
     */
    public function syncForTeam(Team $team): void
    {
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        // Find accounts where owner belongs to this root team
        // Check via teams relationship (user is member of root team or any subteam)
        $accounts = IntegrationsWhatsAppAccount::query()
            ->whereHas('integrationConnection.ownerUser', function ($query) use ($rootTeam) {
                $query->whereHas('teams', function ($tq) use ($rootTeam) {
                    $tq->where('teams.id', $rootTeam->id)
                       ->orWhere('teams.parent_id', $rootTeam->id);
                });
            })
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->get();

        Log::info('[WhatsAppChannelSyncService] Syncing channels for team', [
            'team_id' => $rootTeam->id,
            'accounts_count' => $accounts->count(),
        ]);

        foreach ($accounts as $account) {
            try {
                $this->syncAccount($account);
            } catch (\Exception $e) {
                Log::error('[WhatsAppChannelSyncService] Failed to sync account', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Orphaned Channels entfernen
        $this->removeOrphanedChannels($rootTeam);
    }

    /**
     * Sync einen einzelnen IntegrationsWhatsAppAccount zu CommsChannel
     */
    public function syncAccount(IntegrationsWhatsAppAccount $account): CommsChannel
    {
        // 1. Team aus IntegrationConnection -> OwnerUser holen
        $integrationConnection = $account->integrationConnection;

        if (!$integrationConnection) {
            throw new \Exception("IntegrationsWhatsAppAccount {$account->id} has no IntegrationConnection");
        }

        $ownerUser = $integrationConnection->ownerUser;

        if (!$ownerUser) {
            throw new \Exception("IntegrationConnection {$integrationConnection->id} has no OwnerUser");
        }

        // Regular users don't have team_id - use currentTeam as fallback
        $team = $ownerUser->team ?? $ownerUser->currentTeam;

        if (!$team) {
            throw new \Exception("User {$ownerUser->id} has no Team (neither team nor currentTeam)");
        }

        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        // 2. CommsProviderConnection finden oder erstellen
        $connection = CommsProviderConnection::firstOrCreate(
            [
                'team_id' => $rootTeam->id,
                'provider' => 'whatsapp_meta',
            ],
            [
                'name' => 'WhatsApp Meta',
                'is_active' => true,
                'credentials' => [],
            ]
        );

        // 3. CommsChannel finden oder erstellen
        $channel = CommsChannel::updateOrCreate(
            [
                'team_id' => $rootTeam->id,
                'type' => 'whatsapp',
                'provider' => 'whatsapp_meta',
                'sender_identifier' => $account->phone_number,
            ],
            [
                'comms_provider_connection_id' => $connection->id,
                'name' => $account->title ?: $account->phone_number,
                'visibility' => 'team',
                'is_active' => $account->active,
                'meta' => [
                    'integrations_whatsapp_account_id' => $account->id,
                    'phone_number_id' => $account->phone_number_id,
                    'access_token' => $account->access_token,
                ],
            ]
        );

        Log::info('[WhatsAppChannelSyncService] Channel synced', [
            'channel_id' => $channel->id,
            'account_id' => $account->id,
            'phone_number' => $account->phone_number,
            'is_active' => $account->active,
        ]);

        return $channel;
    }

    /**
     * Entfernt Channels wenn Integration-Account gelöscht wurde
     */
    public function removeOrphanedChannels(Team $team): void
    {
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        // Alle WhatsApp Channels für dieses Team
        $channels = CommsChannel::query()
            ->where('team_id', $rootTeam->id)
            ->where('type', 'whatsapp')
            ->where('provider', 'whatsapp_meta')
            ->get();

        foreach ($channels as $channel) {
            $accountId = $channel->meta['integrations_whatsapp_account_id'] ?? null;

            if (!$accountId) {
                continue;
            }

            // Prüfen ob der verknüpfte Account noch existiert
            $accountExists = IntegrationsWhatsAppAccount::where('id', $accountId)->exists();

            if (!$accountExists) {
                Log::info('[WhatsAppChannelSyncService] Removing orphaned channel', [
                    'channel_id' => $channel->id,
                    'missing_account_id' => $accountId,
                ]);

                $channel->delete();
            }
        }
    }
}
