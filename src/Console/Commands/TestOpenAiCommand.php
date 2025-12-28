<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Services\OpenAiService;

class TestOpenAiCommand extends Command
{
    protected $signature = 'core:test-openai {message=Hallo Welt}';
    protected $description = 'Test OpenAI Service - Sendet eine Nachricht und zeigt die Antwort';

    public function handle()
    {
        $message = $this->argument('message');
        
        $this->info("=== OpenAI Test ===");
        $this->line("Nachricht: {$message}");
        $this->newLine();
        
        try {
            $this->line("Initialisiere OpenAiService...");
            $openAi = app(OpenAiService::class);
            $this->line("✅ OpenAiService initialisiert");
            $this->newLine();
            
            $this->line("Sende Nachricht an OpenAI...");
            $messages = [
                ['role' => 'user', 'content' => $message]
            ];
            
            $this->line("  Messages: " . json_encode($messages, JSON_UNESCAPED_UNICODE));
            $this->line("  Model: gpt-4o-mini");
            $this->line("  Options: tools=false, max_tokens=100");
            $this->newLine();
            
            $startTime = microtime(true);
            $this->line("  Warte auf Antwort...");
            $this->line("  (Timeout: 20 Sekunden)");
            $this->newLine();
            
            try {
                $response = $openAi->chat($messages, 'gpt-4o-mini', [
                    'max_tokens' => 100,
                    'temperature' => 0.7,
                    'tools' => false, // Erstmal ohne Tools testen
                ]);
                
                $duration = round((microtime(true) - $startTime) * 1000);
                $this->line("✅ Antwort erhalten (Dauer: {$duration}ms)");
                $this->newLine();
                
                // Zeige vollständige Response
                $this->line("Vollständige Response:");
                $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->newLine();
                
            } catch (\Throwable $chatError) {
                $duration = round((microtime(true) - $startTime) * 1000);
                $this->error("❌ Fehler nach {$duration}ms:");
                $this->error("  " . $chatError->getMessage());
                $this->error("  Datei: " . $chatError->getFile() . ":" . $chatError->getLine());
                $this->line("  Trace: " . substr($chatError->getTraceAsString(), 0, 800));
                throw $chatError;
            }
            
            $this->info("Antwort:");
            $this->line($response['content'] ?? 'Keine Antwort');
            $this->newLine();
            
            if (isset($response['usage'])) {
                $this->line("Token-Usage:");
                $this->line("  Input: " . ($response['usage']['input_tokens'] ?? $response['usage']['prompt_tokens'] ?? 0));
                $this->line("  Output: " . ($response['usage']['output_tokens'] ?? $response['usage']['completion_tokens'] ?? 0));
                $this->line("  Total: " . ($response['usage']['total_tokens'] ?? 0));
            }
            
            // Zeige auch die rohe Response-Struktur
            $this->newLine();
            $this->line("Response-Struktur:");
            $this->line("  Keys: " . implode(', ', array_keys($response)));
            if (isset($response['usage']['output_tokens']) && $response['usage']['output_tokens'] > 0 && empty($response['content'])) {
                $this->warn("  ⚠️  Output-Tokens vorhanden, aber Content leer!");
                $this->line("  → Möglicherweise falsches Response-Format");
            }
            
            return 0;
            
        } catch (\Throwable $e) {
            $this->error("❌ Fehler: " . $e->getMessage());
            $this->line("Datei: " . $e->getFile() . ":" . $e->getLine());
            $this->line("Trace: " . substr($e->getTraceAsString(), 0, 500));
            return 1;
        }
    }
}

