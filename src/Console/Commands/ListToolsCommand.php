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
                $modulesPath = realpath(__DIR__ . '/../../../../modules');
                if ($modulesPath && is_dir($modulesPath)) {
                    $moduleTools = \Platform\Core\Tools\ToolLoader::loadFromAllModules($modulesPath);
                    foreach ($moduleTools as $tool) {
                        try {
                            $registry->register($tool);
                        } catch (\Throwable $e) {
                            // Ignore
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
            
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

