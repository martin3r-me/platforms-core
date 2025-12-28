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
            // Lade Registry (triggert Auto-Discovery)
            $registry = app(ToolRegistry::class);
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

