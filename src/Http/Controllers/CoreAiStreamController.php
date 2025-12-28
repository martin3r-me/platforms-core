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
        // Debug: Prüfe ob Route erreicht wird - sende sofort eine Test-Nachricht
        // Aber das funktioniert nicht, weil StreamedResponse erst später ausgeführt wird
        // Stattdessen: Prüfe alles VOR dem StreamedResponse
        
        try {
            $user = $request->user();
            if (!$user) {
                // Sende SSE-kompatiblen Fehler
                return new StreamedResponse(function() {
                    echo "data: " . json_encode([
                        'error' => 'Unauthorized - Please log in',
                        'debug' => 'Kein authentifizierter User gefunden'
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                }, 401, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                ]);
            }

            $threadId = (int) $request->query('thread');
            $assistantId = (int) $request->query('assistant');
            // Optional source hints from client (used by CoreContextTool fallback)
            $sourceRoute = $request->query('source_route');
            $sourceModule = $request->query('source_module');
            $sourceUrl = $request->query('source_url');
            if (!$threadId) {
                return new StreamedResponse(function() {
                    echo "data: " . json_encode([
                        'error' => 'thread parameter is required',
                        'debug' => 'Thread-ID fehlt in der Anfrage'
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                }, 422, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                ]);
            }

            $thread = CoreChatThread::find($threadId);
            if (!$thread) {
                return new StreamedResponse(function() use ($threadId) {
                    echo "data: " . json_encode([
                        'error' => 'Thread not found',
                        'debug' => "Thread mit ID {$threadId} wurde nicht gefunden"
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                }, 404, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                ]);
            }
        } catch (\Throwable $e) {
            // Sende Fehler als SSE
            return new StreamedResponse(function() use ($e) {
                echo "data: " . json_encode([
                    'error' => $e->getMessage(),
                    'debug' => "❌ Fehler vor Stream-Start:\nDatei: {$e->getFile()}\nZeile: {$e->getLine()}\n" . substr($e->getTraceAsString(), 0, 500)
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }, 500, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ]);
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

            $response = new StreamedResponse(function () use ($openAi, $messages, &$assistantBuffer, $thread, $assistantId, $sourceRoute, $sourceModule, $sourceUrl, $toolExecutor, $user, $threadId) {
            // Clean buffers to avoid server buffering - SOFORT am Anfang
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            // Initial retry suggestion (client can reconnect faster)
            echo "retry: 500\n\n";
            @flush();
            
            // Debug: Stream gestartet - SOFORT senden, VOR try-catch
            echo "data: " . json_encode([
                'debug' => '✅ Stream-Callback gestartet',
                'user_id' => $user->id ?? 'unknown',
                'thread_id' => $thread->id ?? 'unknown'
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
            
            try {
                // Zusätzliche Debug-Info
                echo "data: " . json_encode([
                    'debug' => '📋 Messages: ' . count($messages) . ' | Assistant ID: ' . ($assistantId ?: 'neu')
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
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
                    echo 'data: ' . json_encode([
                        'tool' => $tool,
                        'debug' => "🔧 Tool gestartet: {$tool}"
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                },
                'tool_executor' => function($toolName, $arguments) use ($toolExecutor) {
                    try {
                        echo 'data: ' . json_encode([
                            'debug' => "⚙️ Führe Tool aus: {$toolName}"
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        
                        $context = ToolContext::fromAuth();
                        $result = $toolExecutor->execute($toolName, $arguments, $context);
                        
                        // Konvertiere ToolResult zu altem Format für Kompatibilität
                        $resultArray = $result->toArray();
                        
                        echo 'data: ' . json_encode([
                            'tool' => $toolName, 
                            'result' => $resultArray,
                            'debug' => "✅ Tool {$toolName} erfolgreich ausgeführt"
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        
                        return $resultArray;
                    } catch (\Throwable $e) {
                        $errorResult = [
                            'ok' => false,
                            'error' => [
                                'code' => 'EXECUTION_ERROR',
                                'message' => $e->getMessage()
                            ]
                        ];
                        
                        echo 'data: ' . json_encode([
                            'tool' => $toolName, 
                            'result' => $errorResult,
                            'debug' => "❌ Tool {$toolName} Fehler: {$e->getMessage()}"
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        
                        return $errorResult;
                    }
                }
            ]);

                // Close stream
                echo "data: [DONE]\n\n";
                @flush();
                
                // Debug: Stream beendet
                echo "data: " . json_encode([
                    'debug' => '✅ Stream beendet',
                    'buffer_length' => mb_strlen($assistantBuffer)
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();

                // Final flush on the same assistant record
                $assistantMessage->update([
                    'content' => $assistantBuffer,
                    'tokens_out' => mb_strlen($assistantBuffer),
                    'meta' => ['is_streaming' => false],
                ]);
            } catch (\Throwable $e) {
                // Sende Fehler an Client
                echo 'data: ' . json_encode([
                    'error' => $e->getMessage(),
                    'debug' => "❌ Fehler im Stream-Callback:\nDatei: {$e->getFile()}\nZeile: {$e->getLine()}\n" . substr($e->getTraceAsString(), 0, 500)
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}


