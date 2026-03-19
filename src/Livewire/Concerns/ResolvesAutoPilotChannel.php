<?php

namespace Platform\Core\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Platform\Crm\Models\CommsChannel;
use Platform\Crm\Models\CrmPhoneNumber;

trait ResolvesAutoPilotChannel
{
    private function resolvePreferredChannel(Model $model): ?CommsChannel
    {
        $teamId = auth()->user()->currentTeam->id;
        $model->loadMissing(['crmContactLinks.contact.phoneNumbers', 'crmContactLinks.contact.emailAddresses']);

        // 1. Check for mobile number with WhatsApp available
        $hasWhatsAppPhone = false;
        foreach ($model->crmContactLinks as $link) {
            foreach ($link->contact?->phoneNumbers ?? [] as $phone) {
                if ($phone->is_active && $phone->whatsapp_status !== CrmPhoneNumber::WHATSAPP_UNAVAILABLE) {
                    $hasWhatsAppPhone = true;
                    break 2;
                }
            }
        }

        if ($hasWhatsAppPhone) {
            $channel = CommsChannel::where('team_id', $teamId)
                ->where('type', 'whatsapp')->where('is_active', true)->first();
            if ($channel) {
                return $channel;
            }
        }

        // 2. Fallback: Email
        $hasEmail = false;
        foreach ($model->crmContactLinks as $link) {
            if ($link->contact?->emailAddresses?->where('is_active', true)->isNotEmpty()) {
                $hasEmail = true;
                break;
            }
        }

        if ($hasEmail) {
            $channel = CommsChannel::where('team_id', $teamId)
                ->where('type', 'email')->where('is_active', true)->first();
            if ($channel) {
                return $channel;
            }
        }

        return null;
    }
}
