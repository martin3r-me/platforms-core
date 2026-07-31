<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Core\Events\TerminalMessageSent;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMessage;

/**
 * Agent-Inbox: der token-freie Delta-Kanal, über den ein Worker im Loop seine
 * ungelesenen Nachrichten konsumiert und beantwortet.
 *
 * Bewusst NUR kontextlose Kanäle (DMs + beteiligte Nicht-Kontext-Threads).
 * Nachrichten in Entity-Kontext-Threads (context_type gesetzt) sind der
 * Resume-Pfad einer Rückfrage und werden dort über den Claim behandelt — sie
 * gehören NICHT in die Gate-Inbox.
 */
class AgentInboxController extends Controller
{
    /**
     * GET /api/terminal/agent/inbox
     * Ungelesene Fremd-Nachrichten des Workers aus kontextlosen Kanälen, ab dem
     * Read-Cursor, älteste zuerst.
     */
    public function inbox(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        if ($userId < 1) {
            return response()->json(['data' => []]);
        }
        $limit = min(50, max(1, (int) $request->integer('limit', 25)));

        // Kanäle, in denen der Worker Mitglied ist — nur ohne Entity-Kontext.
        $memberships = TerminalChannelMember::query()
            ->where('user_id', $userId)
            ->with(['channel' => fn ($q) => $q->whereNull('context_type')])
            ->get()
            ->filter(fn ($m) => $m->channel !== null);

        $items = [];
        foreach ($memberships as $m) {
            $channel = $m->channel;
            $msgs = TerminalMessage::query()
                ->where('channel_id', $channel->id)
                ->whereNull('parent_id')
                ->where('user_id', '!=', $userId)                 // nur Fremd-Nachrichten
                ->when($m->last_read_message_id, fn ($q, $c) => $q->where('id', '>', $c))
                ->with('user:id,name')
                ->orderBy('id')
                ->get(['id', 'channel_id', 'user_id', 'body_plain', 'created_at']);

            foreach ($msgs as $msg) {
                $items[] = [
                    'channel_id' => $channel->id,
                    'channel_type' => $channel->type,
                    'message_id' => $msg->id,
                    'from_user_id' => $msg->user_id,
                    'from' => $msg->user?->name,
                    'body' => $msg->body_plain,
                    'at' => $msg->created_at?->toIso8601String(),
                ];
            }
        }

        // Global älteste zuerst, gedeckelt (der Rest kommt im nächsten Tick).
        usort($items, fn ($a, $b) => $a['message_id'] <=> $b['message_id']);

        return response()->json(['data' => array_slice($items, 0, $limit)]);
    }

    /**
     * POST /api/terminal/agent/inbox/ack  { channel_id, message_id }
     * Read-Cursor eines Kanals setzen (bis inkl. message_id) — behandelt = gelesen.
     */
    public function ack(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        $data = $request->validate([
            'channel_id' => 'required|integer',
            'message_id' => 'required|integer',
        ]);

        $member = TerminalChannelMember::where('channel_id', $data['channel_id'])
            ->where('user_id', $userId)->first();
        if (! $member) {
            return response()->json(['message' => 'Not a member'], 403);
        }
        // Nicht zurückspringen, falls inzwischen weiter gelesen wurde.
        if (($member->last_read_message_id ?? 0) < $data['message_id']) {
            $member->markAsRead($data['message_id']);
        }

        return response()->json(['message' => 'acked']);
    }

    /**
     * POST /api/terminal/agent/reply  { channel_id, body }
     * Antwort des Workers in einen bestehenden Kanal (freundliche Absage, Status,
     * Rückfrage bei Unklarheit). Nur wenn der Worker Mitglied ist.
     */
    public function reply(Request $request): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        $data = $request->validate([
            'channel_id' => 'required|integer',
            'body' => 'required|string|max:5000',
        ]);

        $channel = TerminalChannel::find($data['channel_id']);
        $isMember = $channel && TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $userId)->exists();
        if (! $isMember) {
            return response()->json(['message' => 'Not a member'], 403);
        }

        $bodyPlain = trim($data['body']);
        $message = TerminalMessage::create([
            'channel_id' => $channel->id,
            'user_id' => $userId,
            'body_html' => '<p>'.e($bodyPlain).'</p>',
            'body_plain' => $bodyPlain,
            'has_mentions' => false,
        ]);
        $channel->increment('message_count');
        $channel->update(['last_message_id' => $message->id]);
        // Eigene Nachricht als gelesen markieren.
        TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $userId)->first()?->markAsRead($message->id);

        try {
            TerminalMessageSent::dispatch($channel->id, $message->id, $userId);
        } catch (\Throwable $e) {
            // Broadcast unkritisch — die Nachricht steht bereits.
        }

        return response()->json(['data' => ['id' => $message->id]]);
    }
}
