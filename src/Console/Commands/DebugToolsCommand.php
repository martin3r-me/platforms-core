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
        
        // Stelle sicher, dass App gebootet ist
        $this->line('Prüfe App Status...');
        $this->line("   App gebootet: " . ($this->laravel->isBooted() ? 'Ja' : 'Nein'));
        $this->line("   Running in console: " . ($this->laravel->runningInConsole() ? 'Ja' : 'Nein'));
        $this->newLine();
        
        if (!$this->laravel->isBooted()) {
            $this->warn('App ist noch nicht gebootet - boote jetzt...');
            try {
                $this->laravel->boot();
                $this->line("   ✅ App gebootet");
            } catch (\Throwable $e) {
                $this->error("   ❌ Boot fehlgeschlagen: " . $e->getMessage());
                $this->line("   Datei: " . $e->getFile() . ":" . $e->getLine());
                return 1;
            }
        }
        $this->newLine();

        // 1. Prüfe Registry
        $this->info('1. Tool Registry:');
        try {
            $this->line("   Prüfe ob ToolRegistry Klasse existiert...");
            // Prüfe ob Klasse existiert
            if (!class_exists(ToolRegistry::class)) {
                $this->error("   ❌ ToolRegistry Klasse nicht gefunden!");
                $this->line("   Erwarteter Namespace: " . ToolRegistry::class);
                return 1;
            }
            $this->line("   ✅ ToolRegistry Klasse gefunden");
            
            $this->line("   Versuche ToolRegistry aufzulösen...");
            
            try {
                $registry = app(ToolRegistry::class);
            } catch (\Throwable $resolveError) {
                $this->error("   ❌ Fehler beim Auflösen der ToolRegistry!");
                $this->error("   Fehler: " . $resolveError->getMessage());
                $this->error("   Datei: " . $resolveError->getFile() . ":" . $resolveError->getLine());
                $this->line("   Trace:");
                $this->line(substr($resolveError->getTraceAsString(), 0, 1500));
                
                // Versuche direkt zu instanziieren
                $this->line("   → Versuche direkte Instanziierung...");
                try {
                    $registry = new ToolRegistry();
                    $this->line("   ✅ Direkte Instanziierung erfolgreich");
                } catch (\Throwable $e2) {
                    $this->error("   ❌ Auch direkte Instanziierung fehlgeschlagen: " . $e2->getMessage());
                    return 1;
                }
            }
            
            if (!$registry) {
                $this->error("   ❌ ToolRegistry ist null!");
                return 1;
            }
            $this->line("   ✅ ToolRegistry aufgelöst");
            $this->line("   Typ: " . get_class($registry));
            
            $this->line("   Rufe all() auf...");
            $tools = $registry->all();
            $this->line("   ✅ all() erfolgreich");
            $this->line("   Registrierte Tools: " . count($tools));
            
            if (count($tools) === 0) {
                $this->warn('   ⚠️  Keine Tools registriert!');
                $this->line("   → Versuche Tools manuell zu registrieren...");
                
                // Versuche Tools manuell zu registrieren
                try {
                    $this->line("   Versuche EchoTool zu erstellen...");
                    $echoTool = app(\Platform\Core\Tools\EchoTool::class);
                    $this->line("   ✅ EchoTool erstellt");
                    
                    $this->line("   Registriere EchoTool...");
                    $registry->register($echoTool);
                    $this->line("   ✅ EchoTool registriert");
                    
                    $tools = $registry->all();
                    $this->line("   Neue Anzahl: " . count($tools));
                } catch (\Throwable $e2) {
                    $this->error("   ❌ Manuelle Registrierung fehlgeschlagen!");
                    $this->error("   Fehler: " . $e2->getMessage());
                    $this->error("   Datei: " . $e2->getFile() . ":" . $e2->getLine());
                    $this->line("   Trace: " . substr($e2->getTraceAsString(), 0, 800));
                }
            }
            
            if (count($tools) > 0) {
                $this->line("   Liste der Tools:");
                foreach ($tools as $name => $tool) {
                    $this->line("   - {$name}: " . $tool->getDescription());
                }
            }
        } catch (\Throwable $e) {
            $this->error("   ❌ Fehler: " . $e->getMessage());
            $this->error("   Datei: " . $e->getFile() . ":" . $e->getLine());
            $this->line("   Trace:");
            $this->line(substr($e->getTraceAsString(), 0, 1000));
            return 1;
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
                $userName = $user->name ?? 'no name';
                $this->line("   ✅ User: {$user->id} ({$userName})");
                if (method_exists($user, 'currentTeam')) {
                    $team = $user->currentTeam;
                    $teamInfo = $team ? "{$team->id} ({$team->name})" : "kein Team";
                    $this->line("   Team: {$teamInfo}");
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

