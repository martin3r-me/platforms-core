<?php

namespace Platform\Core\Listeners;

use Platform\Integrations\Events\WhatsAppAccountsSynced;
use Platform\Core\Services\Comms\WhatsAppChannelSyncService;
use Illuminate\Support\Facades\Log;

/**
 * Listener der auf WhatsAppAccountsSynced reagiert und CommsChannels synchronisiert
 */
class SyncWhatsAppChannelsListener
{
    public function __construct(
        private WhatsAppChannelSyncService $syncService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(WhatsAppAccountsSynced $event): void
    {
        Log::info('[SyncWhatsAppChannelsListener] WhatsApp accounts synced, syncing channels', [
            'connection_id' => $event->connection->id,
            'accounts_count' => $event->accounts->count(),
        ]);

        // Sync jeden Account zu CommsChannel
        foreach ($event->accounts as $account) {
            try {
                $this->syncService->syncAccount($account);
            } catch (\Exception $e) {
                Log::error('[SyncWhatsAppChannelsListener] Failed to sync channel for account', [
                    'account_id' => $account->id,
                    'phone_number' => $account->phone_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Orphaned Channels entfernen (für das Team des Connection-Owners)
        try {
            $ownerUser = $event->connection->ownerUser;
            if ($ownerUser && $ownerUser->team) {
                $this->syncService->removeOrphanedChannels($ownerUser->team);
            }
        } catch (\Exception $e) {
            Log::warning('[SyncWhatsAppChannelsListener] Failed to remove orphaned channels', [
                'connection_id' => $event->connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
