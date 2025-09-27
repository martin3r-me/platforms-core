<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

class ToolRegistry
{
    protected array $cachedTools = [];
    
    /**
     * Lade alle verfügbaren Tools (gecacht)
     */
    public function getAllTools(): array
    {
        // Cache automatisch leeren wenn sich Code ändert
        $this->clearCacheIfNeeded();
        
        if (empty($this->cachedTools)) {
            $this->cachedTools = Cache::remember('agent_tools', 86400, function() {
                return $this->generateTools();
            });
        }
        
        return $this->cachedTools;
    }
    
    /**
     * Generiere alle Tools dynamisch (mit OpenAI Limit)
     */
    protected function generateTools(): array
    {
        $tools = [];
        
        // Core Tools
        $coreTools = $this->getCoreTools();
        $tools = array_merge($tools, $coreTools);
        
        // Dynamische Model-Tools mit Limit
        $modelTools = $this->generateModelTools();
        
        // OpenAI Limit: Maximal 128 Tools
        $maxTools = 128;
        $availableSlots = $maxTools - count($coreTools);
        
        if (count($modelTools) > $availableSlots) {
            \Log::warning("🔧 TOO MANY TOOLS:", [
                'total' => count($modelTools),
                'limit' => $availableSlots,
                'filtering' => true
            ]);
            
            // Intelligente Filterung: Nur die wichtigsten Tools
            $modelTools = $this->filterImportantTools($modelTools, $availableSlots);
        }
        
        $tools = array_merge($tools, $modelTools);
        
        \Log::info("🔧 FINAL TOOLS:", [
            'total' => count($tools),
            'core' => count($coreTools),
            'models' => count($modelTools)
        ]);
        
        return $tools;
    }
    
    /**
     * Core Tools
     */
    protected function getCoreTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_time',
                    'description' => 'Aktuelle Zeit abrufen',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[],
                        'required' => []
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_context',
                    'description' => 'Aktuellen Kontext abrufen (User, Team, etc.)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[],
                        'required' => []
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Generiere Tools für alle Models dynamisch
     */
    protected function generateModelTools(): array
    {
        $tools = [];
        $models = $this->getAllModels();
        
        foreach ($models as $modelClass) {
            $modelName = $this->getModelName($modelClass);
            $tableName = $this->getTableName($modelClass);
            
            // Nur wenn Tabelle existiert
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            
            // CRUD Tools generieren
            $tools[] = $this->generateGetTools($modelClass, $modelName);
            $tools[] = $this->generateCreateTools($modelClass, $modelName);
            $tools[] = $this->generateUpdateTools($modelClass, $modelName);
            $tools[] = $this->generateDeleteTools($modelClass, $modelName);
        }
        
        return $tools;
    }
    
    /**
     * Hole alle Models aus der Anwendung
     */
    protected function getAllModels(): array
    {
        $models = [];
        
        // Core Models
        // Dynamisch alle Models finden
        $coreModels = $this->discoverAllModels();
        
        // CRM Models (nur wenn verfügbar)
        if (Schema::hasTable('crm_contacts')) {
            $coreModels[] = 'Platform\\Crm\\Models\\CrmContact';
        }
        if (Schema::hasTable('crm_companies')) {
            $coreModels[] = 'Platform\\Crm\\Models\\CrmCompany';
        }
        
        // OKR Models (nur wenn verfügbar)
        if (Schema::hasTable('okr_objectives')) {
            $coreModels[] = 'Platform\\Okr\\Models\\OkrObjective';
        }
        if (Schema::hasTable('okr_key_results')) {
            $coreModels[] = 'Platform\\Okr\\Models\\OkrKeyResult';
        }
        
        // Nur existierende Models zurückgeben
        foreach ($coreModels as $modelClass) {
            if (class_exists($modelClass)) {
                $models[] = $modelClass;
            }
        }
        
        return $models;
    }
    
    /**
     * Generiere GET Tools für ein Model
     */
    protected function generateGetTools(string $modelClass, string $modelName): array
    {
        $model = new $modelClass();
        $fillable = $model->getFillable();
        
        $properties = [];
        foreach ($fillable as $field) {
            $properties[$field] = [
                'type' => 'string',
                'description' => "Filter nach {$field}"
            ];
        }
        
        return [
            'type' => 'function',
            'function' => [
                'name' => strtolower($modelName) . '_get_all',
                'description' => "Alle {$modelName} Einträge abrufen",
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => []
                ]
            ]
        ];
    }
    
    /**
     * Generiere CREATE Tools für ein Model
     */
    protected function generateCreateTools(string $modelClass, string $modelName): array
    {
        $model = new $modelClass();
        $fillable = $model->getFillable();
        
        $properties = [];
        foreach ($fillable as $field) {
            $description = $this->generateFieldDescription($field, $modelClass, $modelName);
            
            $properties[$field] = [
                'type' => 'string',
                'description' => $description
            ];
        }
        
        return [
            'type' => 'function',
            'function' => [
                'name' => strtolower($modelName) . '_create',
                'description' => "Neuen {$modelName} erstellen",
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $this->getRequiredFields($modelClass)
                ]
            ]
        ];
    }
    
    /**
     * Generiere UPDATE Tools für ein Model
     */
    protected function generateUpdateTools(string $modelClass, string $modelName): array
    {
        $model = new $modelClass();
        $fillable = $model->getFillable();
        
        $properties = [
            'id' => [
                'type' => 'integer',
                'description' => "ID des {$modelName} zum Aktualisieren"
            ]
        ];
        
        foreach ($fillable as $field) {
            $description = $this->generateFieldDescription($field, $modelClass, $modelName, 'Neuer Wert für');
            
            $properties[$field] = [
                'type' => 'string',
                'description' => $description
            ];
        }
        
        return [
            'type' => 'function',
            'function' => [
                'name' => strtolower($modelName) . '_update',
                'description' => "{$modelName} aktualisieren",
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => ['id']
                ]
            ]
        ];
    }
    
    /**
     * Generiere DELETE Tools für ein Model
     */
    protected function generateDeleteTools(string $modelClass, string $modelName): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => strtolower($modelName) . '_delete',
                'description' => "{$modelName} löschen",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => "ID des {$modelName} zum Löschen"
                        ]
                    ],
                    'required' => ['id']
                ]
            ]
        ];
    }
    
    /**
     * Hole Model-Name aus Class-Name
     */
    protected function getModelName(string $modelClass): string
    {
        $parts = explode('\\', $modelClass);
        return end($parts);
    }
    
    /**
     * Hole Table-Name aus Model
     */
    protected function getTableName(string $modelClass): string
    {
        $model = new $modelClass();
        return $model->getTable();
    }
    
    /**
     * Hole required Fields aus Model
     */
    protected function getRequiredFields(string $modelClass): array
    {
        $model = new $modelClass();
        $fillable = $model->getFillable();
        
        // Einfache Heuristik: Felder mit 'name', 'title', 'email' sind meist required
        $required = [];
        foreach ($fillable as $field) {
            if (in_array($field, ['name', 'title', 'email', 'first_name', 'last_name'])) {
                $required[] = $field;
            }
        }
        
        return $required;
    }
    
    /**
     * Generiere intelligente Field-Beschreibungen
     */
    protected function generateFieldDescription(string $field, string $modelClass, string $modelName, string $prefix = ''): string
    {
        $description = $prefix ? "{$prefix} {$field}" : "{$field} für {$modelName}";
        
        // Dynamisch Enum-Werte finden
        $enumValues = $this->getEnumValues($field, $modelClass);
        if ($enumValues) {
            $description .= " - Verfügbare Werte: " . implode(', ', $enumValues);
        }
        
        // Dynamisch Relations finden
        $relations = $this->getFieldRelations($field, $modelClass);
        if ($relations) {
            $description .= " - Relations: " . implode(', ', $relations);
        }
        
        return $description;
    }
    
    /**
     * Finde Enum-Werte dynamisch
     */
    protected function getEnumValues(string $field, string $modelClass): ?array
    {
        try {
            $model = new $modelClass();
            $reflection = new \ReflectionClass($model);
            
            // Suche nach Enum-Properties
            foreach ($reflection->getProperties() as $property) {
                if ($property->getName() === $field) {
                    $type = $property->getType();
                    if ($type && $type instanceof \ReflectionNamedType) {
                        $enumClass = $type->getName();
                        if (enum_exists($enumClass)) {
                            $cases = $enumClass::cases();
                            return array_map(fn($case) => $case->value, $cases);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }
        
        return null;
    }
    
    /**
     * Finde Relations dynamisch
     */
    protected function getFieldRelations(string $field, string $modelClass): ?array
    {
        try {
            $model = new $modelClass();
            $relations = [];
            
            // Suche nach Relations die das Field verwenden
            foreach (['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'] as $relationType) {
                $methods = get_class_methods($model);
                foreach ($methods as $method) {
                    if (str_contains($method, $field)) {
                        $relations[] = $method;
                    }
                }
            }
            
            return !empty($relations) ? $relations : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Entdecke alle Models LOOSE - nur über Composer Autoload
     */
    protected function discoverAllModels(): array
    {
        $models = [];
        
        // Loose: Nur über Composer Autoload alle Klassen scannen
        $autoloader = require base_path('vendor/autoload.php');
        $classMap = $autoloader->getClassMap();
        
        foreach ($classMap as $className => $filePath) {
            // Nur Platform Models
            if (!str_starts_with($className, 'Platform\\')) {
                continue;
            }
            
            // Nur Models (endet mit Model oder ist in Models Namespace)
            if (!str_ends_with($className, 'Model') && !str_contains($className, '\\Models\\')) {
                continue;
            }
            
            // Prüfe ob es ein Eloquent Model ist
            if (class_exists($className) && is_subclass_of($className, \Illuminate\Database\Eloquent\Model::class)) {
                $models[] = $className;
                \Log::info("🔍 LOOSE DISCOVERED MODEL:", ['model' => $className]);
            }
        }
        
        return $models;
    }
    
    /**
     * Filtere wichtige Tools (OpenAI Limit)
     */
    protected function filterImportantTools(array $tools, int $limit): array
    {
        // Priorisiere Tools nach Wichtigkeit
        $priorities = [
            // Höchste Priorität: CRUD für wichtige Models
            'plannerproject' => 10,
            'plannertask' => 10,
            'plannersprint' => 8,
            'crmcontact' => 8,
            'crmcompany' => 8,
            'okrobjective' => 6,
            'okrkeyresult' => 6,
            'corechat' => 4,
            'team' => 4,
            'user' => 4,
        ];
        
        // Gruppiere Tools nach Model
        $groupedTools = [];
        foreach ($tools as $tool) {
            $toolName = $tool['function']['name'] ?? '';
            $modelName = explode('_', $toolName)[0] ?? '';
            $groupedTools[$modelName][] = $tool;
        }
        
        // Sortiere Models nach Priorität
        uksort($groupedTools, function($a, $b) use ($priorities) {
            $priorityA = $priorities[$a] ?? 1;
            $priorityB = $priorities[$b] ?? 1;
            return $priorityB - $priorityA;
        });
        
        // Nimm Tools bis zum Limit
        $filteredTools = [];
        $count = 0;
        
        foreach ($groupedTools as $modelName => $modelTools) {
            if ($count + count($modelTools) > $limit) {
                // Nimm nur die wichtigsten Tools für dieses Model
                $remaining = $limit - $count;
                $modelTools = array_slice($modelTools, 0, $remaining);
            }
            
            $filteredTools = array_merge($filteredTools, $modelTools);
            $count += count($modelTools);
            
            if ($count >= $limit) {
                break;
            }
        }
        
        \Log::info("🔧 FILTERED TOOLS:", [
            'original' => count($tools),
            'filtered' => count($filteredTools),
            'limit' => $limit
        ]);
        
        return $filteredTools;
    }
    
    /**
     * Tools Cache leeren
     */
    public function clearCache(): void
    {
        Cache::forget('agent_tools');
        $this->cachedTools = [];
        \Log::info('🔧 ToolRegistry Cache geleert');
    }
    
    /**
     * Cache automatisch leeren wenn sich Code ändert
     */
    public function clearCacheIfNeeded(): void
    {
        $cacheKey = 'agent_tools_last_modified';
        $currentHash = $this->getCodeHash();
        $lastHash = Cache::get($cacheKey);
        
        if ($currentHash !== $lastHash) {
            $this->clearCache();
            Cache::put($cacheKey, $currentHash, 86400);
            \Log::info('🔧 ToolRegistry Cache automatisch geleert - Code geändert');
        }
    }
    
    /**
     * Generiere Hash des aktuellen Codes
     */
    protected function getCodeHash(): string
    {
        $files = [
            __FILE__, // ToolRegistry.php
            __DIR__ . '/AgentOrchestrator.php',
            __DIR__ . '/IntelligentAgent.php',
        ];
        
        $hash = '';
        foreach ($files as $file) {
            if (file_exists($file)) {
                $hash .= md5_file($file);
            }
        }
        
        return md5($hash);
    }
}