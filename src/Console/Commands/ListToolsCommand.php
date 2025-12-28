<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Tools\ToolRegistry;

class ListToolsCommand extends Command
{
    protected $signature = 'core:list-tools';
    protected $description = 'Listet alle registrierten Tools auf';

    public function handle()
    {
        $this->info("=== Verfügbare Tools ===");
        $this->newLine();
        
        try {
            // Direkte Instanziierung um Memory zu sparen
            $registry = new ToolRegistry();
            
            // Manuell Auto-Discovery auslösen (ohne Container)
            try {
                // Gleicher Pfad wie im CoreServiceProvider: Von core/src/ aus
                $coreSrcPath = realpath(__DIR__ . '/../..'); // platform/core/src/
                $modulesPath = $coreSrcPath ? realpath($coreSrcPath . '/../../modules') : null;
                
                $this->line("🔍 Suche Tools in: " . ($modulesPath ?: 'NICHT GEFUNDEN'));
                if ($modulesPath) {
                    $this->line("   (von: " . __DIR__ . ")");
                }
                
                if ($modulesPath && is_dir($modulesPath)) {
                    $modules = array_filter(glob($modulesPath . '/*'), 'is_dir');
                    $this->line("📦 Gefundene Module: " . count($modules));
                    
                    foreach ($modules as $moduleDir) {
                        $moduleKey = basename($moduleDir);
                        $toolsPath = $moduleDir . '/src/Tools';
                        $this->line("  → {$moduleKey}: " . (is_dir($toolsPath) ? "✅ Tools-Verzeichnis gefunden" : "❌ Kein Tools-Verzeichnis"));
                    }
                    
                    $moduleTools = \Platform\Core\Tools\ToolLoader::loadFromAllModules($modulesPath);
                    $this->line("🔧 Gefundene Tools: " . count($moduleTools));
                    
                    foreach ($moduleTools as $tool) {
                        try {
                            $registry->register($tool);
                            $this->line("  ✅ Registriert: " . $tool->getName());
                        } catch (\Throwable $e) {
                            $this->warn("  ❌ Fehler beim Registrieren: " . $e->getMessage());
                        }
                    }
                } else {
                    $this->warn("⚠️  Modules-Pfad nicht gefunden: {$modulesPath}");
                }
            } catch (\Throwable $e) {
                $this->error("❌ Auto-Discovery Fehler: " . $e->getMessage());
                $this->error("  Datei: " . $e->getFile() . ":" . $e->getLine());
            }
            $this->newLine();
            
            // Core-Tools manuell registrieren
            try {
                if (!$registry->has('tools.list')) {
                    $registry->register(new \Platform\Core\Tools\ListToolsTool($registry));
                }
            } catch (\Throwable $e) {
                // Ignore
            }
            
            try {
                if (!$registry->has('echo')) {
                    $registry->register(new \Platform\Core\Tools\EchoTool());
                }
            } catch (\Throwable $e) {
                // Ignore
            }
            
            $tools = $registry->all();
            
            if (count($tools) === 0) {
                $this->warn("⚠️  Keine Tools registriert!");
                $this->line("→ Tools werden automatisch geladen aus:");
                $this->line("  - platform/core/src/Tools/*.php");
                $this->line("  - platform/modules/{module}/src/Tools/*.php");
                return 0;
            }
            
            $this->line("✅ " . count($tools) . " Tool(s) gefunden:");
            $this->newLine();
            
            // Gruppiere nach Modul
            $grouped = [];
            foreach ($tools as $name => $tool) {
                $module = 'core';
                if (str_contains($name, '.')) {
                    $module = explode('.', $name)[0];
                }
                $grouped[$module][] = [
                    'name' => $name,
                    'description' => $tool->getDescription(),
                ];
            }
            
            foreach ($grouped as $module => $moduleTools) {
                $this->line("📦 Modul: " . ucfirst($module));
                foreach ($moduleTools as $tool) {
                    $this->line("  • {$tool['name']}");
                    $this->line("    {$tool['description']}");
                }
                $this->newLine();
            }
            
            return 0;
        } catch (\Throwable $e) {
            $this->error("❌ Fehler: " . $e->getMessage());
            $this->error("  Datei: " . $e->getFile() . ":" . $e->getLine());
            return 1;
        }
    }
}

