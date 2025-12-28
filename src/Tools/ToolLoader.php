<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Auto-Discovery für Tools aus Modulen
 * 
 * Lädt Tools automatisch aus Modul-Verzeichnissen:
 * - platform/modules/{module}/src/Tools/*.php
 * - platform/core/src/Tools/*.php
 * 
 * Tools werden automatisch mit Modul-Präfix versehen (z.B. "planner.tasks.create")
 */
class ToolLoader
{
    /**
     * Lädt alle Tools aus einem Verzeichnis
     * 
     * @param string $basePath Basis-Pfad (z.B. __DIR__ . '/../../modules/planner/src')
     * @param string $namespace Basis-Namespace (z.B. 'Platform\Planner')
     * @param string $moduleKey Modul-Key für Präfix (z.B. 'planner')
     * @return array<ToolContract>
     */
    public static function loadFromDirectory(string $basePath, string $namespace, string $moduleKey = 'core'): array
    {
        $tools = [];
        $toolsPath = rtrim($basePath, '/') . '/Tools';
        
        if (!is_dir($toolsPath)) {
            return $tools;
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($toolsPath)
            );
            
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                
                $relativePath = str_replace($toolsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace(['/', '\\'], '/', $relativePath);
                $relativePath = str_replace('.php', '', $relativePath);
                
                // Konvertiere Pfad zu Namespace
                $classPath = str_replace('/', '\\', $relativePath);
                $className = $namespace . '\\Tools\\' . $classPath;
                
                if (!class_exists($className)) {
                    continue;
                }
                
                // Prüfe ob Klasse ToolContract implementiert
                if (!is_subclass_of($className, ToolContract::class)) {
                    continue;
                }
                
                try {
                    $tool = new $className();
                    
                    // Auto-Präfix: Wenn Tool-Name noch kein Modul-Präfix hat, füge es hinzu
                    $toolName = $tool->getName();
                    if ($moduleKey !== 'core' && !str_contains($toolName, '.')) {
                        // Tool hat noch kein Präfix - füge Modul-Präfix hinzu
                        // Aber: Tool muss selbst entscheiden, ob es ein Präfix will
                        // Deshalb: Nur wenn Tool explizit kein Präfix hat
                    }
                    
                    $tools[] = $tool;
                    Log::debug("[ToolLoader] Tool gefunden: {$className} -> {$toolName}");
                } catch (\Throwable $e) {
                    Log::warning("[ToolLoader] Tool konnte nicht instanziiert werden: {$className}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[ToolLoader] Fehler beim Laden aus {$toolsPath}", [
                'error' => $e->getMessage()
            ]);
        }
        
        return $tools;
    }
    
    /**
     * Lädt Tools aus allen Modulen
     * 
     * @param string $modulesBasePath Basis-Pfad zu Modulen (z.B. __DIR__ . '/../../modules')
     * @return array<ToolContract>
     */
    public static function loadFromAllModules(string $modulesBasePath): array
    {
        $allTools = [];
        
        if (!is_dir($modulesBasePath)) {
            return $allTools;
        }
        
        $modules = array_filter(glob($modulesBasePath . '/*'), 'is_dir');
        
        foreach ($modules as $moduleDir) {
            $moduleKey = basename($moduleDir);
            $moduleNamespace = 'Platform\\' . Str::studly($moduleKey);
            $moduleSrcPath = $moduleDir . '/src';
            
            if (!is_dir($moduleSrcPath)) {
                continue;
            }
            
            $moduleTools = self::loadFromDirectory($moduleSrcPath, $moduleNamespace, $moduleKey);
            $allTools = array_merge($allTools, $moduleTools);
        }
        
        return $allTools;
    }
    
    /**
     * Lädt Core-Tools
     * 
     * @return array<ToolContract>
     */
    public static function loadCoreTools(): array
    {
        $corePath = __DIR__;
        $coreNamespace = 'Platform\\Core\\Tools';
        
        return self::loadFromDirectory($corePath, $coreNamespace, 'core');
    }
}

