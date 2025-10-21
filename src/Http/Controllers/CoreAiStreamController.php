<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Platform\Core\Services\OpenAiService;
use Platform\Core\Models\CoreChatThread;
use Platform\Core\Models\CoreChatMessage;

class CoreAiStreamController extends Controller
{
    public function stream(Request $request, OpenAiService $openAi): StreamedResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $threadId = (int) $request->query('thread');
        if (!$threadId) {
            abort(422, 'thread parameter is required');
        }

        $thread = CoreChatThread::find($threadId);
        if (!$thread) {
            abort(404, 'Thread not found');
        }

        // Build messages context
        $messages = CoreChatMessage::where('thread_id', $threadId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($m) {
                return [
                    'role' => $m->role,
                    'content' => $m->content,
                ];
            })
            ->toArray();

        // Optional: add brief system instruction to improve early tokens
        if (!empty($messages)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => 'Antworte kurz, präzise und auf Deutsch. Streame Tokens zügig.',
            ]);
        }

        $assistantBuffer = '';

        $response = new StreamedResponse(function () use ($openAi, $messages, &$assistantBuffer, $thread) {
            // Clean buffers to avoid server buffering
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            // Initial retry suggestion (client can reconnect faster)
            echo "retry: 500\n\n";
            @flush();

            // Stream deltas
            $openAi->streamChat($messages, function (string $delta) use (&$assistantBuffer) {
                $assistantBuffer .= $delta;
                echo 'data: ' . json_encode(['delta' => $delta], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            });

            // Close stream
            echo "data: [DONE]\n\n";
            @flush();

            // Persist final assistant message
            if ($assistantBuffer !== '') {
                CoreChatMessage::create([
                    'core_chat_id' => $thread->core_chat_id,
                    'thread_id' => $thread->id,
                    'role' => 'assistant',
                    'content' => $assistantBuffer,
                    'tokens_out' => mb_strlen($assistantBuffer),
                ]);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}


