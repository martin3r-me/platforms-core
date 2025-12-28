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
    public function stream(Request $request): StreamedResponse
    {
        // Lazy-load Dependencies im Stream-Callback, um Fehler früher zu erkennen
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

        // Build messages context - mit Fehlerbehandlung
        try {
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
        } catch (\Throwable $e) {
            return new StreamedResponse(function() use ($e) {
                echo "data: " . json_encode([
                    'error' => 'Messages Error',
                    'debug' => "❌ Fehler beim Laden der Messages: {$e->getMessage()}"
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }, 500, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ]);
        }

        // Kontext wird zentral im OpenAiService prependet
        $assistantBuffer = '';

        $response = new StreamedResponse(function () use ($messages, &$assistantBuffer, $thread, $assistantId, $sourceRoute, $sourceModule, $sourceUrl, $user, $threadId) {
            // Debug-Infos sammeln
            $debugInfos = [];
            $debugInfos[] = '✅ Stream-Callback gestartet';
            $debugInfos[] = "User ID: {$user->id} | Thread ID: {$thread->id} | Messages: " . count($messages);
            
            // Clean buffers to avoid server buffering - SOFORT am Anfang
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            // Initial retry suggestion (client can reconnect faster)
            echo "retry: 500\n\n";
            @flush();

            // Status-Update: Stream gestartet
            echo "data: " . json_encode([
                'status' => [
                    'text' => 'Stream initialisiert',
                    'type' => 'info',
                    'icon' => '🚀'
                ],
                'debug' => '✅ Stream-Callback gestartet',
                'user_id' => $user->id ?? 'unknown',
                'thread_id' => $thread->id ?? 'unknown',
                'messages_count' => count($messages)
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
            
            // Einfacher Ansatz: Erstmal ohne Tools, nur Chat
            try {
                // Status: Lade OpenAI Service
                echo "data: " . json_encode([
                    'status' => [
                        'text' => 'Lade OpenAI Service...',
                        'type' => 'info',
                        'icon' => '🔄'
                    ]
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
                
                $openAi = app(OpenAiService::class);
                
                // Status: Service geladen
                echo "data: " . json_encode([
                    'status' => [
                        'text' => 'OpenAI Service bereit',
                        'type' => 'success',
                        'icon' => '✅'
                    ]
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
                
                // ToolExecutor laden (für Tool-Aufrufe)
                // WICHTIG: Außerhalb des try-catch definieren, damit es im use-Closure verfügbar ist
                $toolExecutor = null;
                try {
                    echo "data: " . json_encode([
                        'status' => [
                            'text' => 'Lade Tools...',
                            'type' => 'info',
                            'icon' => '🔧'
                        ],
                        'debug' => 'Starte Tool-Loading...'
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                    
                    // Schritt 1: ToolRegistry laden (KOMPLETT ohne Container-Interaktion)
                    // INNOVATIV: Direkte Instanz, keine Container-Bindung - maximale Isolation
                    $registry = null;
                    $toolExecutor = null;
                    
                    try {
                        echo "data: " . json_encode([
                            'debug' => 'Schritt 1: Erstelle ToolRegistry (ohne Container)...'
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        
                        // Direkt instanziieren - KEINE Container-Interaktion
                        $registry = new \Platform\Core\Tools\ToolRegistry();
                        
                        echo "data: " . json_encode([
                            'debug' => '✅ ToolRegistry instanziiert: ' . get_class($registry)
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        
                        // Optional: Im Container binden (nur wenn app() verfügbar ist)
                        try {
                            if (function_exists('app') && app() instanceof \Illuminate\Contracts\Container\Container) {
                                app()->instance(\Platform\Core\Tools\ToolRegistry::class, $registry);
                                echo "data: " . json_encode([
                                    'debug' => '✅ ToolRegistry im Container gebunden'
                                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                            }
                        } catch (\Throwable $bindError) {
                            // Binding fehlgeschlagen - kein Problem, wir verwenden die Instanz direkt
                            echo "data: " . json_encode([
                                'debug' => '⚠️ Container-Binding fehlgeschlagen (nicht kritisch): ' . $bindError->getMessage()
                            ], JSON_UNESCAPED_UNICODE) . "\n\n";
                            @flush();
                        }
                    } catch (\Throwable $e1) {
                        echo "data: " . json_encode([
                            'error' => 'ToolRegistry Fehler',
                            'debug' => '❌ ToolRegistry Fehler: ' . $e1->getMessage() . ' in ' . $e1->getFile() . ':' . $e1->getLine() . "\nTrace: " . substr($e1->getTraceAsString(), 0, 500)
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        // Nicht werfen - ohne Tools weiter machen
                        $registry = null;
                        $toolExecutor = null;
                    }
                    
                    // Schritt 2: Prüfe vorhandene Tools (nur wenn Registry verfügbar)
                    $tools = []; // Initialisiere als leeres Array
                    if ($registry === null) {
                        echo "data: " . json_encode([
                            'status' => [
                                'text' => 'Tools nicht verfügbar - nur Chat',
                                'type' => 'warning',
                                'icon' => '⚠️'
                            ],
                            'debug' => 'ToolRegistry nicht verfügbar - Chat ohne Tools'
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        $toolExecutor = null;
                    } else {
                        try {
                            $tools = $registry->all();
                            echo "data: " . json_encode([
                                'debug' => 'Vorhandene Tools: ' . count($tools)
                            ], JSON_UNESCAPED_UNICODE) . "\n\n";
                            @flush();
                            
                            if (count($tools) === 0) {
                                echo "data: " . json_encode([
                                    'debug' => 'Keine Tools - registriere EchoTool...'
                                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                                
                                // Manuell EchoTool registrieren (keine Dependencies)
                                $echoTool = new \Platform\Core\Tools\EchoTool();
                                $registry->register($echoTool);
                                $tools = $registry->all();
                                
                                echo "data: " . json_encode([
                                    'debug' => 'EchoTool registriert - Tools: ' . count($tools)
                                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                            }
                        } catch (\Throwable $e2) {
                            echo "data: " . json_encode([
                                'error' => 'Tool-Registrierung Fehler',
                                'debug' => 'Tool-Registrierung Fehler: ' . $e2->getMessage() . ' in ' . $e2->getFile() . ':' . $e2->getLine()
                            ], JSON_UNESCAPED_UNICODE) . "\n\n";
                            @flush();
                            // Nicht werfen - ohne Tools weiter machen
                            $tools = [];
                            $toolExecutor = null;
                        }
                        
                        // Schritt 3: ToolExecutor erstellen (nur wenn Registry verfügbar)
                        if ($registry !== null && count($tools) > 0) {
                            try {
                                echo "data: " . json_encode([
                                    'debug' => 'Erstelle ToolExecutor...'
                                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                                
                                $toolExecutor = new \Platform\Core\Tools\ToolExecutor($registry);
                                
                                echo "data: " . json_encode([
                                    'debug' => 'ToolExecutor erstellt: ' . get_class($toolExecutor)
                                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                                
                                // Erfolg
                                echo "data: " . json_encode([
                                    'status' => [
                                        'text' => count($tools) . ' Tool(s) verfügbar',
                                        'type' => 'success',
                                        'icon' => '✅'
                                    ],
                                    'debug' => 'Tools erfolgreich geladen'
                                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                            } catch (\Throwable $e3) {
                                echo "data: " . json_encode([
                                    'error' => 'ToolExecutor Fehler',
                                    'debug' => 'ToolExecutor Fehler: ' . $e3->getMessage() . ' in ' . $e3->getFile() . ':' . $e3->getLine()
                                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                                // Nicht werfen - ohne Tools weiter machen
                                $toolExecutor = null;
                            }
                        } else {
                            // Registry vorhanden, aber keine Tools
                            $toolExecutor = null;
                        }
                    }
                } catch (\Throwable $e) {
                    // Falls ToolExecutor nicht geladen werden kann, ohne Tools weiter
                    $toolExecutor = null;
                    echo "data: " . json_encode([
                        'status' => [
                            'text' => 'Tools nicht verfügbar - nur Chat',
                            'type' => 'warning',
                            'icon' => '⚠️'
                        ],
                        'debug' => 'ToolExecutor Fehler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . ' | Trace: ' . substr($e->getTraceAsString(), 0, 500)
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                }
                
                // Assistant-Message erstellen
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
                $flushInterval = 0.35;
                $flushThreshold = 800;
            $pendingSinceLastFlush = 0;

                // Status: Starte OpenAI Stream
                echo "data: " . json_encode([
                    'status' => [
                        'text' => 'Starte OpenAI Chat...',
                        'type' => 'info',
                        'icon' => '🚀'
                    ],
                    'debug' => 'Rufe streamChat auf...'
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();

                // Debug: Prüfe ob ToolExecutor verfügbar ist
                echo "data: " . json_encode([
                    'debug' => 'ToolExecutor Status: ' . ($toolExecutor ? 'verfügbar' : 'nicht verfügbar')
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();

                // Stream MIT Tools (wenn verfügbar)
                // WICHTIG: $toolExecutor muss im use-Closure verfügbar sein
                try {
                    echo "data: " . json_encode([
                        'debug' => 'Starte streamChat-Aufruf...'
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                    
                    // Options vorbereiten
                    $streamOptions = [
                        'with_context' => true,
                        'source_route' => $sourceRoute,
                        'source_module' => $sourceModule,
                        'source_url' => $sourceUrl,
                    ];
                    
                    // Tools aktivieren wenn ToolExecutor verfügbar
                    // WICHTIG: null = Tools aktivieren (OpenAiService lädt sie dann)
                    // false = Tools deaktivieren
                    if ($toolExecutor !== null) {
                        // null bedeutet: Tools aktivieren (OpenAiService ruft getAvailableTools() auf)
                        $streamOptions['tools'] = null;
                        $streamOptions['on_tool_start'] = function(string $tool) {
                            echo 'data: ' . json_encode([
                                'tool' => $tool,
                                'status' => [
                                    'text' => "Tool: {$tool}",
                                    'type' => 'tool',
                                    'icon' => '🔧'
                                ]
                            ], JSON_UNESCAPED_UNICODE) . "\n\n";
                            @flush();
                        };
                        $streamOptions['tool_executor'] = function($toolName, $arguments) use ($toolExecutor) {
                            try {
                                echo 'data: ' . json_encode([
                                    'status' => [
                                        'text' => "Führe {$toolName} aus...",
                                        'type' => 'info',
                                        'icon' => '⚙️'
                                    ]
                                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                                
                                $context = \Platform\Core\Tools\ToolContext::fromAuth();
                                $result = $toolExecutor->execute($toolName, $arguments, $context);
                                
                                // Konvertiere ToolResult zu altem Format für Kompatibilität
                                $resultArray = $result->toArray();
                                
                                echo 'data: ' . json_encode([
                                    'tool' => $toolName, 
                                    'result' => $resultArray,
                                    'status' => [
                                        'text' => "{$toolName}: Erfolg",
                                        'type' => 'success',
                                        'icon' => '✅'
                                    ]
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
                                    'status' => [
                                        'text' => "{$toolName}: Fehler",
                                        'type' => 'error',
                                        'icon' => '❌'
                                    ],
                                    'debug' => "Tool-Fehler: {$e->getMessage()}"
                                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                                
                                return $errorResult;
                            }
                        };
                    } else {
                        $streamOptions['tools'] = false; // Tools deaktivieren
                    }
                    
                    echo "data: " . json_encode([
                        'debug' => 'Rufe streamChat auf mit ' . count($streamOptions) . ' Optionen...'
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                    
                    // Delta-Callback
                    $deltaCallback = function (string $delta) use (&$assistantBuffer, &$lastFlushAt, $flushInterval, $flushThreshold, &$pendingSinceLastFlush, $assistantMessage) {
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
                    };
                    
                    echo "data: " . json_encode([
                        'debug' => 'Delta-Callback erstellt, rufe streamChat auf...'
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                    
                    // WICHTIG: streamChat blockiert - der Stream läuft synchron
                    // Deshalb müssen wir sicherstellen, dass der Output-Buffer nicht geschlossen wird
                    // Jetzt streamChat aufrufen - mit expliziter Fehlerbehandlung
                    try {
                        echo "data: " . json_encode([
                            'debug' => 'Starte streamChat (blockiert bis Stream beendet)...'
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        
                        $openAi->streamChat($messages, $deltaCallback, model: 'gpt-4o-mini', options: $streamOptions);
                        
                        // Diese Zeile wird nur erreicht, wenn streamChat erfolgreich beendet wurde
                        echo "data: " . json_encode([
                            'debug' => 'streamChat-Aufruf abgeschlossen'
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                    } catch (\Throwable $streamChatError) {
                        // Fehler direkt beim streamChat-Aufruf
                        echo "data: " . json_encode([
                            'error' => 'streamChat Fehler',
                            'debug' => "Fehler beim streamChat-Aufruf: {$streamChatError->getMessage()} in {$streamChatError->getFile()}:{$streamChatError->getLine()}\nTrace: " . substr($streamChatError->getTraceAsString(), 0, 1000)
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                        throw $streamChatError; // Re-throw um in outer catch zu landen
                    }
                } catch (\Throwable $streamError) {
                    // Fehler beim StreamChat
                    echo "data: " . json_encode([
                        'error' => 'StreamChat Fehler',
                        'debug' => "Fehler beim streamChat: {$streamError->getMessage()} in {$streamError->getFile()}:{$streamError->getLine()}\nTrace: " . substr($streamError->getTraceAsString(), 0, 1000)
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                    throw $streamError; // Re-throw um in outer catch zu landen
                }

                // Status: Stream erfolgreich beendet
                echo "data: " . json_encode([
                    'status' => [
                        'text' => 'Antwort abgeschlossen',
                        'type' => 'success',
                        'icon' => '✅'
                    ]
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();

            // Close stream
            echo "data: [DONE]\n\n";
            @flush();
                
                // Debug: Stream beendet
                $debugInfos[] = '✅ Stream beendet';
                $debugInfos[] = 'Buffer-Länge: ' . mb_strlen($assistantBuffer);

            // Final flush on the same assistant record
            $assistantMessage->update([
                'content' => $assistantBuffer,
                'tokens_out' => mb_strlen($assistantBuffer),
                'meta' => ['is_streaming' => false],
            ]);
                
                // Speichere Debug-Infos als Chat-Nachricht (nur wenn Buffer leer oder viele Debug-Infos)
                if (mb_strlen($assistantBuffer) === 0 || count($debugInfos) > 5) {
                    try {
                        \Platform\Core\Models\CoreChatMessage::create([
                            'core_chat_id' => $thread->core_chat_id,
                            'thread_id' => $thread->id,
                            'role' => 'assistant',
                            'content' => "🔍 Debug-Infos:\n" . implode("\n", $debugInfos),
                            'tokens_in' => 0,
                        ]);
                    } catch (\Throwable $saveError) {
                        // Ignore save errors
                    }
                }
            } catch (\Throwable $e) {
                // Debug-Infos sammeln
                $debugInfos[] = "❌ Fehler im Stream-Callback:";
                $debugInfos[] = "Datei: {$e->getFile()}";
                $debugInfos[] = "Zeile: {$e->getLine()}";
                $debugInfos[] = "Fehler: {$e->getMessage()}";
                $debugInfos[] = "Trace: " . substr($e->getTraceAsString(), 0, 2000);
                
                // Sende Fehler an Client
                echo 'data: ' . json_encode([
                    'error' => $e->getMessage(),
                    'debug' => "❌ Fehler im Stream-Callback:\nDatei: {$e->getFile()}\nZeile: {$e->getLine()}\nFehler: {$e->getMessage()}\n" . substr($e->getTraceAsString(), 0, 1000)
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
                
                // Speichere Debug-Infos als Chat-Nachricht
                try {
                    \Platform\Core\Models\CoreChatMessage::create([
                        'core_chat_id' => $thread->core_chat_id,
                        'thread_id' => $thread->id,
                        'role' => 'assistant',
                        'content' => "❌ Stream-Fehler aufgetreten!\n\n🔍 Debug-Infos:\n" . implode("\n", $debugInfos),
                        'tokens_in' => 0,
                    ]);
                } catch (\Throwable $saveError) {
                    // Ignore save errors
                }
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Nginx buffering deaktivieren

        return $response;
    }
}


