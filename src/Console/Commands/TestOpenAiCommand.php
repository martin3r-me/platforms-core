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
            
            $response = $openAi->chat($messages, 'gpt-4o-mini', [
                'max_tokens' => 100,
                'temperature' => 0.7,
                'tools' => false, // Erstmal ohne Tools testen
            ]);
            
            $this->line("✅ Antwort erhalten");
            $this->newLine();
            
            $this->info("Antwort:");
            $this->line($response['content'] ?? 'Keine Antwort');
            $this->newLine();
            
            if (isset($response['usage'])) {
                $this->line("Token-Usage:");
                $this->line("  Input: " . ($response['usage']['prompt_tokens'] ?? 0));
                $this->line("  Output: " . ($response['usage']['completion_tokens'] ?? 0));
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

