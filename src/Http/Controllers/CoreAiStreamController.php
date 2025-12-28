<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Platform\Core\Services\OpenAiService;
use Platform\Core\Models\CoreChatThread;
use Platform\Core\Models\CoreChatMessage;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Contracts\ToolContext;

class CoreAiStreamController extends Controller
{
    public function stream(Request $request, OpenAiService $openAi, ToolExecutor $toolExecutor): StreamedResponse
    {
        \Log::info('[CoreAiStreamController] Stream request received', [
            'user_id' => $request->user()?->id,
            'thread' => $request->query('thread'),
            'assistant' => $request->query('assistant'),
        ]);
        
        try {
            $user = $request->user();
            if (!$user) {
                \Log::warning('[CoreAiStreamController] No authenticated user');
                abort(401);
            }

            $threadId = (int) $request->query('thread');
            $assistantId = (int) $request->query('assistant');
            // Optional source hints from client (used by CoreContextTool fallback)
            $sourceRoute = $request->query('source_route');
            $sourceModule = $request->query('source_module');
            $sourceUrl = $request->query('source_url');
            if (!$threadId) {
                \Log::warning('[CoreAiStreamController] Missing thread parameter');
                abort(422, 'thread parameter is required');
            }

            $thread = CoreChatThread::find($threadId);
            if (!$thread) {
                \Log::warning('[CoreAiStreamController] Thread not found', ['thread_id' => $threadId]);
                abort(404, 'Thread not found');
            }
            
            \Log::info('[CoreAiStreamController] Starting stream', [
                'thread_id' => $threadId,
                'assistant_id' => $assistantId,
            ]);
        } catch (\Throwable $e) {
            \Log::error('[CoreAiStreamController] Error before stream start', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
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

            $response = new StreamedResponse(function () use ($openAi, $messages, &$assistantBuffer, $thread, $assistantId, $sourceRoute, $sourceModule, $sourceUrl, $toolExecutor) {
            try {
                \Log::info('[CoreAiStreamController] Stream callback started');
                
                // Clean buffers to avoid server buffering
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }

                // Initial retry suggestion (client can reconnect faster)
                echo "retry: 500\n\n";
                @flush();
                
                \Log::debug('[CoreAiStreamController] SSE headers sent');

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
                'tool_executor' => function($toolName, $arguments) use ($toolExecutor) {
                    try {
                        $context = ToolContext::fromAuth();
                        $result = $toolExecutor->execute($toolName, $arguments, $context);
                        
                        // Konvertiere ToolResult zu altem Format für Kompatibilität
                        $resultArray = $result->toArray();
                        
                        echo 'data: ' . json_encode(['tool' => $toolName, 'result' => $resultArray], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        
                        return $resultArray;
                    } catch (\Throwable $e) {
                        \Log::error('[CoreAiStreamController] Tool execution error', [
                            'tool' => $toolName,
                            'error' => $e->getMessage()
                        ]);
                        
                        $errorResult = [
                            'ok' => false,
                            'error' => [
                                'code' => 'EXECUTION_ERROR',
                                'message' => $e->getMessage()
                            ]
                        ];
                        
                        echo 'data: ' . json_encode(['tool' => $toolName, 'result' => $errorResult], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        
                        return $errorResult;
                    }
                }
            ]);

            // Close stream
            echo "data: [DONE]\n\n";
            @flush();
            
            \Log::info('[CoreAiStreamController] Stream completed', [
                'buffer_length' => mb_strlen($assistantBuffer),
            ]);

            // Final flush on the same assistant record
            $assistantMessage->update([
                'content' => $assistantBuffer,
                'tokens_out' => mb_strlen($assistantBuffer),
                'meta' => ['is_streaming' => false],
            ]);
            } catch (\Throwable $e) {
                \Log::error('[CoreAiStreamController] Error in stream callback', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Sende Fehler an Client
                echo 'data: ' . json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}


