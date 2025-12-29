<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Tools\ToolOrchestrator;
use Platform\Core\Tools\ToolChainPlanner;
use Platform\Core\Tools\ToolDiscoveryService;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\User;
use Platform\Core\Models\Team;

/**
 * Test-Command für Tool-Orchestrierung
 * 
 * Testet die komplette Tool-Orchestrierung inkl. Dependencies, Chain-Planning, etc.
 */
class TestToolOrchestrationCommand extends Command
{
    protected $signature = 'core:test-tool-orchestration 
                            {tool : Tool-Name (z.B. "planner.projects.create")}
                            {--args= : JSON-Argumente (z.B. \'{"name":"Test"}\')}
                            {--plan : Zeige Chain-Plan vor Ausführung}
                            {--discover : Zeige Discovery-Ergebnisse}';

    protected $description = 'Testet Tool-Orchestrierung mit Dependencies, Chain-Planning und Discovery';

    public function handle()
    {
        $toolName = $this->argument('tool');
        $argsJson = $this->option('args') ?? '{}';
        $showPlan = $this->option('plan');
        $showDiscover = $this->option('discover');

        $this->info("=== Tool-Orchestrierung Test ===");
        $this->line("Tool: {$toolName}");
        $this->line("Argumente: {$argsJson}");
        $this->newLine();

        try {
            // Argumente parsen
            $arguments = json_decode($argsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("❌ Ungültige JSON-Argumente: " . json_last_error_msg());
                return 1;
            }

            // Services initialisieren
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("1. Services initialisieren");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            
            $registry = app(ToolRegistry::class);
            $executor = new ToolExecutor($registry);
            $orchestrator = new ToolOrchestrator($executor, $registry);
            $planner = new ToolChainPlanner($registry);
            $discovery = new ToolDiscoveryService($registry);

            $this->line("✅ ToolRegistry: " . count($registry->all()) . " Tools");
            $this->line("✅ ToolExecutor: Initialisiert");
            $this->line("✅ ToolOrchestrator: Initialisiert");
            $this->line("✅ ToolChainPlanner: Initialisiert");
            $this->line("✅ ToolDiscoveryService: Initialisiert");
            $this->newLine();

            // Tool prüfen
            $tool = $registry->get($toolName);
            if (!$tool) {
                $this->error("❌ Tool '{$toolName}' nicht gefunden!");
                $this->line("Verfügbare Tools:");
                foreach ($registry->all() as $name => $t) {
                    $this->line("  - {$name}");
                }
                return 1;
            }

            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("2. Tool-Informationen");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("Name: " . $tool->getName());
            $this->line("Beschreibung: " . $tool->getDescription());
            
            // Prüfe Contracts
            $hasDeps = $tool instanceof \Platform\Core\Contracts\ToolDependencyContract;
            $hasMetadata = $tool instanceof \Platform\Core\Contracts\ToolMetadataContract;
            $this->line("ToolDependencyContract: " . ($hasDeps ? "✅" : "❌"));
            $this->line("ToolMetadataContract: " . ($hasMetadata ? "✅" : "❌"));
            $this->newLine();

            // Discovery (optional)
            if ($showDiscover) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("3. Tool Discovery");
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                
                $intent = "Projekt erstellen";
                $found = $discovery->findByIntent($intent);
                $this->line("Suche nach Intent: '{$intent}'");
                $this->line("Gefundene Tools: " . count($found));
                foreach ($found as $foundTool) {
                    $this->line("  - " . $foundTool->getName());
                }
                $this->newLine();
            }

            // Chain-Plan (optional)
            if ($showPlan) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("4. Chain-Plan (Pre-Flight)");
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                
                $context = $this->createTestContext();
                $plan = $planner->planChain($toolName, $arguments, $context);
                
                $this->line("Haupt-Tool: " . $plan['main_tool']);
                $this->line("Tools in Chain: " . count($plan['tools']));
                $this->line("Ausführungsreihenfolge:");
                foreach ($plan['order'] as $index => $orderedTool) {
                    $this->line("  " . ($index + 1) . ". {$orderedTool}");
                }
                
                if (!empty($plan['missing'])) {
                    $this->warn("⚠️  Fehlende Tools: " . implode(', ', $plan['missing']));
                }
                
                if (!empty($plan['warnings'])) {
                    $this->warn("⚠️  Warnungen:");
                    foreach ($plan['warnings'] as $warning) {
                        $this->line("  - {$warning}");
                    }
                }
                $this->newLine();
            }

            // Context erstellen
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("5. Test-Context erstellen");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            
            $context = $this->createTestContext();
            $this->line("✅ Mock-User erstellt: ID " . $context->user->id);
            $this->line("✅ Mock-Team erstellt: ID " . ($context->team?->id ?? 'null'));
            $this->newLine();

            // Orchestrator ausführen
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("6. Tool-Orchestrierung ausführen");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            
            $this->line("Rufe executeWithDependencies auf...");
            $this->line("  Tool: {$toolName}");
            $this->line("  Argumente: " . json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->newLine();

            $result = $orchestrator->executeWithDependencies(
                $toolName,
                $arguments,
                $context,
                maxDepth: 5,
                planFirst: $showPlan
            );

            // Ergebnis anzeigen
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("7. Ergebnis");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            
            if ($result->success) {
                $this->info("✅ Tool erfolgreich ausgeführt!");
                $this->line("Daten:");
                $this->line(json_encode($result->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                
                // Prüfe, ob Dependency-Ergebnis zurückgegeben wurde
                if (isset($result->data['requires_user_input']) && $result->data['requires_user_input']) {
                    $this->newLine();
                    $this->warn("⚠️  Dependency-Ergebnis zurückgegeben (User-Input erforderlich)");
                    $this->line("Nächstes Tool: " . ($result->data['next_tool'] ?? 'N/A'));
                    $this->line("Message: " . ($result->data['message'] ?? 'N/A'));
                }
            } else {
                $this->error("❌ Tool-Fehler!");
                $this->line("Fehler: " . $result->error);
                $this->line("Code: " . ($result->errorCode ?? 'N/A'));
                if (!empty($result->metadata)) {
                    $this->line("Metadata: " . json_encode($result->metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
            }

            $this->newLine();
            $this->info("✅ Test abgeschlossen!");
            return 0;

        } catch (\Throwable $e) {
            $this->error("❌ Fehler: " . $e->getMessage());
            $this->line("Datei: " . $e->getFile() . ":" . $e->getLine());
            $this->line("Trace:");
            $this->line(substr($e->getTraceAsString(), 0, 1000));
            return 1;
        }
    }

    /**
     * Erstellt Test-Context mit Mock-User und Mock-Team
     */
    private function createTestContext(): ToolContext
    {
        $mockUser = new User([
            'id' => 999,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $mockTeam = new Team([
            'id' => 999,
            'name' => 'Test Team',
        ]);

        return new ToolContext($mockUser, $mockTeam);
    }
}

