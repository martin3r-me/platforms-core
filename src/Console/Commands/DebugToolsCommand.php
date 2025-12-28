<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Contracts\ToolContext;

class DebugToolsCommand extends Command
{
    protected $signature = 'core:debug-tools';
    protected $description = 'Debug Tools - Zeigt alle registrierten Tools und testet sie';

    public function handle()
    {
        $this->info('=== Tool Debug ===');
        $this->newLine();

        // 1. Prüfe Registry
        $this->info('1. Tool Registry:');
        try {
            $registry = app(ToolRegistry::class);
            $tools = $registry->all();
            $this->line("   Registrierte Tools: " . count($tools));
            
            if (count($tools) === 0) {
                $this->warn('   ⚠️  Keine Tools registriert!');
            } else {
                foreach ($tools as $name => $tool) {
                    $this->line("   - {$name}: " . $tool->getDescription());
                }
            }
        } catch (\Throwable $e) {
            $this->error("   ❌ Fehler: " . $e->getMessage());
            $this->line("   Trace: " . $e->getTraceAsString());
        }
        $this->newLine();

        // 2. Prüfe Executor
        $this->info('2. Tool Executor:');
        try {
            $executor = app(ToolExecutor::class);
            $this->line("   ✅ Executor verfügbar");
        } catch (\Throwable $e) {
            $this->error("   ❌ Fehler: " . $e->getMessage());
        }
        $this->newLine();

        // 3. Teste EchoTool
        $this->info('3. Teste EchoTool:');
        try {
            $registry = app(ToolRegistry::class);
            $echoTool = $registry->get('echo');
            
            if (!$echoTool) {
                $this->warn('   ⚠️  EchoTool nicht gefunden!');
            } else {
                $this->line("   ✅ EchoTool gefunden");
                
                // Teste Ausführung
                $context = ToolContext::fromAuth();
                $result = $echoTool->execute([
                    'message' => 'Test Message',
                    'number' => 42
                ], $context);
                
                if ($result->success) {
                    $this->line("   ✅ Ausführung erfolgreich");
                    $this->line("   Ergebnis: " . json_encode($result->data, JSON_PRETTY_PRINT));
                } else {
                    $this->error("   ❌ Ausführung fehlgeschlagen: " . $result->error);
                }
            }
        } catch (\Throwable $e) {
            $this->error("   ❌ Fehler: " . $e->getMessage());
            $this->line("   Trace: " . $e->getTraceAsString());
        }
        $this->newLine();

        // 4. Prüfe OpenAiService
        $this->info('4. OpenAiService Tools:');
        try {
            $openAiService = app(\Platform\Core\Services\OpenAiService::class);
            $reflection = new \ReflectionClass($openAiService);
            $method = $reflection->getMethod('getAvailableTools');
            $method->setAccessible(true);
            $tools = $method->invoke($openAiService);
            
            $this->line("   Verfügbare Tools für OpenAI: " . count($tools));
            foreach ($tools as $tool) {
                $name = $tool['function']['name'] ?? $tool['name'] ?? 'unknown';
                $this->line("   - {$name}");
            }
        } catch (\Throwable $e) {
            $this->error("   ❌ Fehler: " . $e->getMessage());
        }
        $this->newLine();

        // 5. Prüfe ob User authentifiziert ist
        $this->info('5. Auth Status:');
        try {
            $user = auth()->user();
            if ($user) {
                $this->line("   ✅ User: {$user->id} ({$user->name ?? 'no name'})");
                if (method_exists($user, 'currentTeam')) {
                    $team = $user->currentTeam;
                    $this->line("   Team: " . ($team ? "{$team->id} ({$team->name})" : "kein Team"));
                }
            } else {
                $this->warn('   ⚠️  Kein User authentifiziert');
            }
        } catch (\Throwable $e) {
            $this->error("   ❌ Fehler: " . $e->getMessage());
        }
        $this->newLine();

        $this->info('=== Debug abgeschlossen ===');
        
        return 0;
    }
}

