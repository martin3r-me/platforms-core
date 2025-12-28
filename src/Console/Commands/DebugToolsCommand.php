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
            
            $this->line("   Versuche ToolRegistry direkt zu instanziieren (spart Memory)...");
            try {
                // Direkte Instanziierung statt app() - vermeidet afterResolving Callbacks
                $registry = new ToolRegistry();
                $this->line("   ✅ Direkte Instanziierung erfolgreich");
            } catch (\Throwable $e2) {
                $this->error("   ❌ Direkte Instanziierung fehlgeschlagen: " . $e2->getMessage());
                $this->line("   → Versuche über app()...");
                try {
                    $registry = app(ToolRegistry::class);
                    $this->line("   ✅ Über app() erfolgreich");
                } catch (\Throwable $resolveError) {
                    $this->error("   ❌ Auch app() fehlgeschlagen!");
                    $this->error("   Fehler: " . $resolveError->getMessage());
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
            $this->line("   Versuche ToolExecutor direkt zu instanziieren...");
            // Direkte Instanziierung mit der bereits erstellten Registry
            $executor = new ToolExecutor($registry);
            $this->line("   ✅ Executor direkt instanziiert");
        } catch (\Throwable $e) {
            $this->error("   ❌ Fehler: " . $e->getMessage());
            $this->line("   Datei: " . $e->getFile() . ":" . $e->getLine());
        }
        $this->newLine();

        // 3. Teste EchoTool
        $this->info('3. Teste EchoTool:');
        try {
            $echoTool = $registry->get('echo');
            
            if (!$echoTool) {
                $this->warn('   ⚠️  EchoTool nicht gefunden!');
            } else {
                $this->line("   ✅ EchoTool gefunden");
                
                // Teste Ausführung - ohne Auth (spart Memory)
                $this->line("   Erstelle Test-Context...");
                $user = new class {
                    public $id = 999;
                };
                $context = new ToolContext($user);
                
                $this->line("   Führe EchoTool aus...");
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
            $this->line("   Datei: " . $e->getFile() . ":" . $e->getLine());
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

        // 5. Prüfe ob User authentifiziert ist (überspringen - spart Memory)
        $this->info('5. Auth Status:');
        $this->warn('   ⚠️  Übersprungen (spart Memory)');
        $this->line("   → Auth-Check würde DB-Queries auslösen");
        $this->newLine();

        $this->info('=== Debug abgeschlossen ===');
        
        return 0;
    }
}

