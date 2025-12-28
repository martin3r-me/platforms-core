<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Services\OpenAiService;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolExecutor;

class TestOpenAiCommand extends Command
{
    protected $signature = 'core:test-openai {message=Hallo Welt} {--stream : Teste Stream statt Chat} {--tools : Aktiviere Tools}';
    protected $description = 'Test OpenAI Service - Sendet eine Nachricht und zeigt die Antwort (mit ausführlichem Debugging)';

    public function handle()
    {
        $message = $this->argument('message');
        $useStream = $this->option('stream');
        $useTools = $this->option('tools');
        
        $this->info("=== OpenAI Test (mit Debugging) ===");
        $this->line("Nachricht: {$message}");
        $this->line("Modus: " . ($useStream ? "Stream" : "Chat"));
        $this->line("Tools: " . ($useTools ? "Aktiviert" : "Deaktiviert"));
        $this->newLine();
        
        try {
            // Schritt 1: OpenAiService laden
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("SCHRITT 1: OpenAiService laden");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            try {
                $openAi = app(OpenAiService::class);
                $this->line("✅ OpenAiService geladen: " . get_class($openAi));
            } catch (\Throwable $e) {
                $this->error("❌ OpenAiService Fehler: " . $e->getMessage());
                $this->line("  Datei: " . $e->getFile() . ":" . $e->getLine());
                throw $e;
            }
            $this->newLine();
            
            // Schritt 2: ToolRegistry laden (wenn Tools aktiviert)
            $toolExecutor = null;
            $registry = null;
            if ($useTools) {
                $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->line("SCHRITT 2: ToolRegistry & ToolExecutor laden");
                $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                
                try {
                    $this->line("2.1: Erstelle ToolRegistry (direkt, ohne Container)...");
                    $registry = new ToolRegistry();
                    $this->line("✅ ToolRegistry instanziiert: " . get_class($registry));
                    
                    $this->line("2.2: Prüfe vorhandene Tools...");
                    $tools = $registry->all();
                    $this->line("  Vorhandene Tools: " . count($tools));
                    
                    if (count($tools) === 0) {
                        $this->line("2.3: Keine Tools - registriere EchoTool...");
                        $echoTool = new \Platform\Core\Tools\EchoTool();
                        $registry->register($echoTool);
                        $tools = $registry->all();
                        $this->line("✅ EchoTool registriert - Tools: " . count($tools));
                    }
                    
                    $this->line("2.4: Erstelle ToolExecutor...");
                    $toolExecutor = new ToolExecutor($registry);
                    $this->line("✅ ToolExecutor erstellt: " . get_class($toolExecutor));
                    
                    // Optional: Im Container binden
                    try {
                        app()->instance(ToolRegistry::class, $registry);
                        $this->line("✅ ToolRegistry im Container gebunden");
                    } catch (\Throwable $bindError) {
                        $this->warn("⚠️  Container-Binding fehlgeschlagen (nicht kritisch): " . $bindError->getMessage());
                    }
                    
                } catch (\Throwable $e) {
                    $this->error("❌ Tool-Loading Fehler: " . $e->getMessage());
                    $this->line("  Datei: " . $e->getFile() . ":" . $e->getLine());
                    $this->line("  Trace: " . substr($e->getTraceAsString(), 0, 500));
                    $this->warn("⚠️  Weiter ohne Tools...");
                    $toolExecutor = null;
                }
                $this->newLine();
            }
            
            // Schritt 3: Messages vorbereiten
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("SCHRITT 3: Messages vorbereiten");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $messages = [
                ['role' => 'user', 'content' => $message]
            ];
            $this->line("Messages: " . json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->newLine();
            
            // Schritt 4: Options vorbereiten
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("SCHRITT 4: Options vorbereiten");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $options = [
                'max_tokens' => 100,
                'temperature' => 0.7,
                'with_context' => true,
            ];
            
            if ($useTools && $toolExecutor !== null) {
                $options['tools'] = null; // null = aktivieren
                $options['on_tool_start'] = function(string $tool) {
                    $this->line("🔧 Tool gestartet: {$tool}");
                };
                $options['tool_executor'] = function($toolName, $arguments) use ($toolExecutor) {
                    try {
                        $this->line("⚙️  Führe Tool aus: {$toolName}");
                        $this->line("  Argumente: " . json_encode($arguments, JSON_UNESCAPED_UNICODE));
                        
                        $context = \Platform\Core\Tools\ToolContext::fromAuth();
                        $result = $toolExecutor->execute($toolName, $arguments, $context);
                        $resultArray = $result->toArray();
                        
                        $this->line("✅ Tool erfolgreich: " . json_encode($resultArray, JSON_UNESCAPED_UNICODE));
                        return $resultArray;
                    } catch (\Throwable $e) {
                        $this->error("❌ Tool Fehler: " . $e->getMessage());
                        return [
                            'ok' => false,
                            'error' => ['code' => 'EXECUTION_ERROR', 'message' => $e->getMessage()]
                        ];
                    }
                };
                $this->line("✅ Tools aktiviert");
            } else {
                $options['tools'] = false;
                $this->line("✅ Tools deaktiviert");
            }
            $this->newLine();
            
            // Schritt 5: OpenAI aufrufen
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("SCHRITT 5: OpenAI aufrufen");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("Model: gpt-4o-mini");
            $this->line("Options: " . json_encode($options, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->newLine();
            
            $startTime = microtime(true);
            $response = null;
            $streamedContent = '';
            
            if ($useStream) {
                $this->line("5.1: Starte streamChat...");
                $this->line("5.2: WICHTIG - streamChat ruft getAvailableTools() auf, BEVOR der Stream startet!");
                $this->line("     Das ist der kritische Punkt - prüfe ob getAvailableTools() funktioniert...");
                $this->newLine();
                
                // Test: Prüfe Container-Bindung
                $this->line("5.3: Prüfe Container-Bindung...");
                if ($useTools && isset($registry)) {
                    $isBound = app()->bound(ToolRegistry::class);
                    $this->line("  ToolRegistry gebunden: " . ($isBound ? "Ja" : "Nein"));
                    
                    if ($isBound) {
                        try {
                            $boundRegistry = app()->make(ToolRegistry::class);
                            $this->line("  Gebundene Registry-Instanz: " . get_class($boundRegistry));
                            $this->line("  Ist es die gleiche Instanz? " . ($boundRegistry === $registry ? "Ja ✅" : "Nein ❌"));
                            
                            $boundTools = $boundRegistry->all();
                            $this->line("  Tools in gebundener Registry: " . count($boundTools));
                            
                            $originalTools = $registry->all();
                            $this->line("  Tools in originaler Registry: " . count($originalTools));
                            
                            if (count($boundTools) === 0 && count($originalTools) > 0) {
                                $this->warn("  ⚠️  PROBLEM: Gebundene Registry ist leer, aber originale hat Tools!");
                                $this->line("  → Lösung: Gebundene Registry mit Tools füllen...");
                                
                                // Kopiere Tools in gebundene Registry
                                foreach ($originalTools as $tool) {
                                    $boundRegistry->register($tool);
                                }
                                $this->line("  ✅ Tools in gebundene Registry kopiert");
                                
                                $boundTools = $boundRegistry->all();
                                $this->line("  Tools in gebundener Registry (nach Kopie): " . count($boundTools));
                            }
                        } catch (\Throwable $e) {
                            $this->error("  ❌ Fehler beim Zugriff auf gebundene Registry: " . $e->getMessage());
                        }
                    }
                }
                $this->newLine();
                
                // Test: Rufe getAvailableTools() manuell auf (wie streamChat es tut)
                try {
                    $this->line("5.4: Teste getAvailableTools() manuell (Schritt für Schritt)...");
                    $this->newLine();
                    
                    // Schritt 1: Prüfe ob $this->app existiert
                    $this->line("  5.4.1: Prüfe OpenAiService->app...");
                    $reflection = new \ReflectionClass($openAi);
                    $app = null;
                    try {
                        $appProperty = $reflection->getProperty('app');
                        $appProperty->setAccessible(true);
                        $app = $appProperty->getValue($openAi);
                        $this->line("    ✅ app Property gefunden: " . get_class($app));
                        $this->line("    app ist Container: " . ($app instanceof \Illuminate\Contracts\Container\Container ? "Ja" : "Nein"));
                    } catch (\Throwable $e) {
                        $this->error("    ❌ app Property nicht gefunden: " . $e->getMessage());
                        $this->line("    → Verwende app() Helper stattdessen...");
                        $app = app();
                    }
                    $this->newLine();
                    
                    // Schritt 2: Prüfe Container-Bindung
                    $this->line("  5.4.2: Prüfe Container-Bindung...");
                    try {
                        if ($app === null) {
                            $this->warn("    ⚠️  app ist null - verwende app() Helper");
                            $app = app();
                        }
                        $isBound = $app->bound(ToolRegistry::class);
                        $this->line("    ToolRegistry gebunden: " . ($isBound ? "Ja" : "Nein"));
                        
                        if ($isBound) {
                            $toolRegistryFromApp = $app->make(ToolRegistry::class);
                            $this->line("    ✅ ToolRegistry aus Container geholt: " . get_class($toolRegistryFromApp));
                            $this->line("    Ist es die gleiche Instanz wie unsere? " . ($toolRegistryFromApp === $registry ? "Ja ✅" : "Nein ❌"));
                            
                            $toolsFromApp = $toolRegistryFromApp->all();
                            $this->line("    Tools in Registry aus Container: " . count($toolsFromApp));
                            
                            if (count($toolsFromApp) > 0) {
                                foreach ($toolsFromApp as $tool) {
                                    $this->line("      - " . $tool->getName() . ": " . $tool->getDescription());
                                }
                            }
                        } else {
                            $this->warn("    ⚠️  ToolRegistry NICHT gebunden - getAvailableTools() wird neue Instanz erstellen!");
                        }
                    } catch (\Throwable $e) {
                        $this->error("    ❌ Fehler beim Container-Zugriff: " . $e->getMessage());
                    }
                    $this->newLine();
                    
                    // Schritt 3: Rufe getAvailableTools() auf
                    $this->line("  5.4.3: Rufe getAvailableTools() auf...");
                    $method = $reflection->getMethod('getAvailableTools');
                    $method->setAccessible(true);
                    
                    // Hook: Ersetze convertToolToOpenAiFormat temporär mit Debug-Version
                    $convertMethod = $reflection->getMethod('convertToolToOpenAiFormat');
                    $convertMethod->setAccessible(true);
                    
                    $availableTools = $method->invoke($openAi);
                    $this->line("    ✅ getAvailableTools() erfolgreich - " . count($availableTools) . " Tools gefunden");
                    
                    if (count($availableTools) > 0) {
                        $this->line("    Tools:");
                        foreach ($availableTools as $toolDef) {
                            $name = $toolDef['function']['name'] ?? $toolDef['name'] ?? 'unknown';
                            $desc = $toolDef['function']['description'] ?? $toolDef['description'] ?? 'no description';
                            $this->line("      - {$name}: {$desc}");
                        }
                    } else {
                        $this->warn("    ⚠️  PROBLEM: getAvailableTools() findet keine Tools!");
                        $this->line("    → Mögliche Ursachen:");
                        $this->line("      1. ToolRegistry ist leer (aber wir haben 1 Tool registriert!)");
                        $this->line("      2. convertToolToOpenAiFormat() schlägt fehl (wird still abgefangen)");
                        $this->line("      3. Tool-Format ist falsch");
                        
                        // Debug: Teste convertToolToOpenAiFormat manuell
                        if ($useTools && isset($registry)) {
                            $this->line("    → Teste convertToolToOpenAiFormat() manuell...");
                            $allTools = $registry->all();
                            foreach ($allTools as $tool) {
                                try {
                                    $toolDef = $convertMethod->invoke($openAi, $tool);
                                    $this->line("      ✅ Tool '{$tool->getName()}' konvertiert: " . json_encode($toolDef, JSON_UNESCAPED_UNICODE));
                                } catch (\Throwable $e) {
                                    $this->error("      ❌ Tool '{$tool->getName()}' Konvertierung fehlgeschlagen: " . $e->getMessage());
                                    $this->line("         Datei: " . $e->getFile() . ":" . $e->getLine());
                                }
                            }
                        }
                    }
                } catch (\Throwable $toolsError) {
                    $this->error("❌ getAvailableTools() Fehler:");
                    $this->error("  " . $toolsError->getMessage());
                    $this->error("  Datei: " . $toolsError->getFile() . ":" . $toolsError->getLine());
                    $this->line("  Trace: " . substr($toolsError->getTraceAsString(), 0, 1000));
                    $this->warn("⚠️  streamChat wird wahrscheinlich auch fehlschlagen!");
                    $this->newLine();
                }
                $this->newLine();
                
                try {
                    $this->line("5.5: Rufe streamChat auf...");
                    $this->line("5.6: Stream-Debugging aktiviert - alle Events werden geloggt");
                    $this->newLine();
                    
                    $eventCount = 0;
                    $deltaCount = 0;
                    $toolCallCount = 0;
                    $lastEvent = null;
                    $events = [];
                    
                    // Debug-Callback für alle Events
                    $options['on_debug'] = function($event, $data) use (&$eventCount, &$events, &$toolCallCount) {
                        $eventCount++;
                        $events[] = [
                            'event' => $event,
                            'keys' => array_keys($data),
                            'has_tool_call' => isset($data['name']) || isset($data['tool_name']),
                        ];
                        
                        if (isset($data['name']) || isset($data['tool_name'])) {
                            $toolCallCount++;
                        }
                        
                        // Zeige die ersten 10 Events direkt an
                        if ($eventCount <= 10) {
                            echo "\n[Event #{$eventCount}] {$event}: " . json_encode(array_keys($data), JSON_UNESCAPED_UNICODE) . "\n";
                        }
                    };
                    
                    $openAi->streamChat($messages, function (string $delta) use (&$streamedContent, &$deltaCount) {
                        $streamedContent .= $delta;
                        $deltaCount++;
                        echo $delta; // Direkte Ausgabe
                    }, model: 'gpt-4o-mini', options: $options);
                    
                    $duration = round((microtime(true) - $startTime) * 1000);
                    $this->newLine();
                    $this->line("✅ Stream abgeschlossen (Dauer: {$duration}ms)");
                    $this->line("Streamed Content Länge: " . mb_strlen($streamedContent));
                    $this->line("Delta-Count: {$deltaCount}");
                    $this->line("Event-Count: {$eventCount}");
                    $this->line("Tool-Call-Count: {$toolCallCount}");
                    $this->newLine();
                    
                    // Zeige Event-Übersicht
                    if (count($events) > 0) {
                        $this->line("Event-Übersicht (erste 20):");
                        foreach (array_slice($events, 0, 20) as $idx => $event) {
                            $this->line("  " . ($idx + 1) . ". {$event['event']}: " . implode(', ', $event['keys']));
                        }
                        if (count($events) > 20) {
                            $this->line("  ... und " . (count($events) - 20) . " weitere Events");
                        }
                        $this->newLine();
                    }
                    
                    if (mb_strlen($streamedContent) === 0) {
                        $this->warn("⚠️  PROBLEM: Stream-Content ist leer!");
                        $this->line("  → Mögliche Ursachen:");
                        $this->line("     1. OpenAI sendet keine Antwort");
                        $this->line("     2. Stream wird nicht korrekt geparst");
                        $this->line("     3. Tool-Aufrufe werden nicht verarbeitet");
                    } else {
                        $this->info("Antwort (aus Stream):");
                        $this->line($streamedContent);
                    }
                    
                } catch (\Throwable $streamError) {
                    $duration = round((microtime(true) - $startTime) * 1000);
                    $this->error("❌ Stream-Fehler nach {$duration}ms:");
                    $this->error("  " . $streamError->getMessage());
                    $this->error("  Datei: " . $streamError->getFile() . ":" . $streamError->getLine());
                    $this->line("  Trace: " . substr($streamError->getTraceAsString(), 0, 1000));
                    throw $streamError;
                }
            } else {
                $this->line("5.1: Starte chat...");
                try {
                    $response = $openAi->chat($messages, 'gpt-4o-mini', $options);
                    
                    $duration = round((microtime(true) - $startTime) * 1000);
                    $this->line("✅ Antwort erhalten (Dauer: {$duration}ms)");
                    $this->newLine();
                    
                    $this->info("Antwort:");
                    $content = $response['content'] ?? 'Keine Antwort';
                    if (is_array($content)) {
                        $this->warn("  ⚠️  Content ist ein Array!");
                        $this->line("  Content: " . json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    } else {
                        $this->line($content);
                    }
                    $this->newLine();
                    
                    if (isset($response['usage'])) {
                        $this->line("Token-Usage:");
                        $this->line("  Input: " . ($response['usage']['input_tokens'] ?? $response['usage']['prompt_tokens'] ?? 0));
                        $this->line("  Output: " . ($response['usage']['output_tokens'] ?? $response['usage']['completion_tokens'] ?? 0));
                        $this->line("  Total: " . ($response['usage']['total_tokens'] ?? 0));
                    }
                    
                } catch (\Throwable $chatError) {
                    $duration = round((microtime(true) - $startTime) * 1000);
                    $this->error("❌ Chat-Fehler nach {$duration}ms:");
                    $this->error("  " . $chatError->getMessage());
                    $this->error("  Datei: " . $chatError->getFile() . ":" . $chatError->getLine());
                    $this->line("  Trace: " . substr($chatError->getTraceAsString(), 0, 1000));
                    throw $chatError;
                }
            }
            
            $this->newLine();
            $this->info("✅ Test erfolgreich abgeschlossen!");
            return 0;
            
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->error("❌ FEHLER:");
            $this->error("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->error("Message: " . $e->getMessage());
            $this->error("Datei: " . $e->getFile() . ":" . $e->getLine());
            $this->line("Trace:");
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}

