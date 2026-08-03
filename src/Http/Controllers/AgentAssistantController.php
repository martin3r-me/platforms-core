<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMessage;

/**
 * Assistent-Claim (Delegations-DM, v1): die DM-Kanäle zwischen dem Assistenten (Token-User) und
 * seinem betreuten User („Chef"), die UNGELESENE Nachrichten des Chefs haben — je Kanal die neuen
 * Nachrichten PLUS jüngster Verlauf als Antwort-Kontext. Nur echte DMs (type='dm'), nur der Chef
 * als Gegenüber; KEIN Zugriff auf fremde/private Chats. Beantwortet wird über /terminal/agent/reply,
 * als gelesen markiert über /terminal/agent/inbox/ack.
 */
class AgentAssistantController extends Controller
{
    /**
     * GET /api/core/agent/assistant/inbox  { served_user_id }
     */
    public function inbox(Request $request): JsonResponse
    {
        $workerId = (int) $request->user()?->id;
        $chefId = (int) $request->integer('served_user_id');
        if ($workerId < 1 || $chefId < 1) {
            return response()->json(['data' => []]);
        }

        // DM-Kanäle, in denen BEIDE Mitglied sind (Delegations-DM Chef↔Assistent).
        $workerChannelIds = TerminalChannelMember::where('user_id', $workerId)->pluck('channel_id');
        $chefChannelIds = TerminalChannelMember::where('user_id', $chefId)->pluck('channel_id');
        $sharedDmIds = TerminalChannel::query()
            ->ofType('dm')
            ->whereIn('id', $workerChannelIds)
            ->whereIn('id', $chefChannelIds)
            ->pluck('id');

        $result = [];
        foreach ($sharedDmIds as $channelId) {
            $cursor = TerminalChannelMember::where('channel_id', $channelId)
                ->where('user_id', $workerId)->value('last_read_message_id');

            $unread = TerminalMessage::query()
                ->where('channel_id', $channelId)
                ->whereNull('parent_id')
                ->where('user_id', $chefId)                       // nur der Chef als Absender
                ->when($cursor, fn ($q, $c) => $q->where('id', '>', $c))
                ->orderBy('id')
                ->get(['id', 'user_id', 'body_plain', 'created_at']);

            if ($unread->isEmpty()) {
                continue;
            }

            // Jüngster Verlauf beider Parteien (chronologisch) als Kontext zum Antworten.
            $history = TerminalMessage::query()
                ->where('channel_id', $channelId)
                ->whereNull('parent_id')
                ->with('user:id,name')
                ->orderByDesc('id')
                ->limit(15)
                ->get(['id', 'user_id', 'body_plain', 'created_at'])
                ->sortBy('id')
                ->values();

            $result[] = [
                'channel_id' => (int) $channelId,
                'chef_id' => $chefId,
                'latest_message_id' => (int) $unread->last()->id,   // für ack
                'unread' => $unread->map(fn ($m) => [
                    'message_id' => (int) $m->id,
                    'body' => $m->body_plain,
                    'at' => $m->created_at?->toIso8601String(),
                ])->values(),
                'history' => $history->map(fn ($m) => [
                    'from' => $m->user?->name,
                    'from_user_id' => (int) $m->user_id,
                    'body' => $m->body_plain,
                    'at' => $m->created_at?->toIso8601String(),
                ])->values(),
            ];
        }

        return response()->json(['data' => $result]);
    }
}
