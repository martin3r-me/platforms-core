<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Platform\Core\Services\OpenAiService;
use Platform\Core\Models\CoreChatThread;
use Platform\Core\Models\CoreChatMessage;
use Platform\Core\Tools\CoreDataReadTool;
use Platform\Core\Tools\CoreDataProxy;
use Platform\Core\Tools\CoreWriteProxy;

class CoreAiStreamController extends Controller
{
    public function stream(Request $request, OpenAiService $openAi, CoreDataReadTool $dataReadTool): StreamedResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $threadId = (int) $request->query('thread');
        $assistantId = (int) $request->query('assistant');
        // Optional source hints from client (used by CoreContextTool fallback)
        $sourceRoute = $request->query('source_route');
        $sourceModule = $request->query('source_module');
        $sourceUrl = $request->query('source_url');
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

        // Kontext wird zentral im OpenAiService prependet

        $assistantBuffer = '';

        $response = new StreamedResponse(function () use ($openAi, $messages, &$assistantBuffer, $thread, $assistantId, $sourceRoute, $sourceModule, $sourceUrl, $dataReadTool) {
            // Clean buffers to avoid server buffering
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            // Initial retry suggestion (client can reconnect faster)
            echo "retry: 500\n\n";
            @flush();

            // Use provided assistant placeholder or create a new one
            if ($assistantId) {
                $assistantMessage = CoreChatMessage::find($assistantId);
                if (!$assistantMessage) {
                    $assistantMessage = CoreChatMessage::create([
                        'core_chat_id' => $thread->core_chat_id,
                        'thread_id' => $thread->id,
                        'role' => 'assistant',
                        'content' => '',
                        'meta' => ['is_streaming' => true],
                    ]);
                } else {
                    $assistantMessage->update([
                        'content' => '',
                        'tokens_out' => 0,
                        'meta' => ['is_streaming' => true],
                    ]);
                }
            } else {
                $assistantMessage = CoreChatMessage::create([
                    'core_chat_id' => $thread->core_chat_id,
                    'thread_id' => $thread->id,
                    'role' => 'assistant',
                    'content' => '',
                    'meta' => ['is_streaming' => true],
                ]);
            }

            $lastFlushAt = microtime(true);
            $flushInterval = 0.35; // seconds
            $flushThreshold = 800;  // characters
            $pendingSinceLastFlush = 0;

            // Stream deltas with tool execution
            $openAi->streamChat($messages, function (string $delta) use (&$assistantBuffer, &$lastFlushAt, $flushInterval, $flushThreshold, &$pendingSinceLastFlush, $assistantMessage) {
                $assistantBuffer .= $delta;
                $pendingSinceLastFlush += mb_strlen($delta);

                echo 'data: ' . json_encode(['delta' => $delta], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();

                $now = microtime(true);
                if ($pendingSinceLastFlush >= $flushThreshold || ($now - $lastFlushAt) >= $flushInterval) {
                    // Batched update to reduce write amplification
                    $assistantMessage->update([
                        'content' => $assistantBuffer,
                        'tokens_out' => mb_strlen($assistantBuffer),
                        'meta' => ['is_streaming' => true],
                    ]);
                    $pendingSinceLastFlush = 0;
                    $lastFlushAt = $now;
                }
            }, options: [
                'with_context' => true,
                'source_route' => $sourceRoute,
                'source_module' => $sourceModule,
                'source_url' => $sourceUrl,
                'on_tool_start' => function(string $tool) {
                    echo 'data: ' . json_encode(['tool' => $tool], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                },
                'tool_executor' => function($toolName, $arguments) {
                    if ($toolName === 'data_read') {
                        $proxy = app(CoreDataProxy::class);
                        $entity = $arguments['entity'] ?? '';
                        $operation = $arguments['operation'] ?? '';
                        $result = $proxy->executeRead($entity, $operation, $arguments, ['trace_id' => bin2hex(random_bytes(8))]);
                        echo 'data: ' . json_encode(['tool' => 'data_read', 'result' => $result], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        return $result;
                    }
                    if ($toolName === 'data_write') {
                        $proxy = app(CoreWriteProxy::class);
                        $entity = $arguments['entity'] ?? '';
                        $operation = $arguments['operation'] ?? '';
                        $result = $proxy->executeCommand($entity, $operation, $arguments, ['trace_id' => bin2hex(random_bytes(8))]);
                        echo 'data: ' . json_encode(['tool' => 'data_write', 'result' => $result], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        return $result;
                    }
                    return null;
                }
            ]);

            // Close stream
            echo "data: [DONE]\n\n";
            @flush();

            // Final flush on the same assistant record
            $assistantMessage->update([
                'content' => $assistantBuffer,
                'tokens_out' => mb_strlen($assistantBuffer),
                'meta' => ['is_streaming' => false],
            ]);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}


