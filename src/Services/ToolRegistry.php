<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
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
     * Generiere alle Tools dynamisch
     */
    protected function generateTools(): array
    {
        $tools = [];
        
        // Core Tools
        $tools = array_merge($tools, $this->getCoreTools());
        
        // Dynamische Model-Tools
        $tools = array_merge($tools, $this->generateModelTools());
        
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
        $coreModels = [
            'Platform\\Core\\Models\\CoreChat',
            'Platform\\Core\\Models\\CoreChatMessage',
            'Platform\\Core\\Models\\CoreChatEvent',
            'Platform\\Core\\Models\\Team',
            'Platform\\Core\\Models\\User',
        ];
        
        // Planner Models (nur wenn verfügbar)
        if (Schema::hasTable('planner_projects')) {
            $coreModels[] = 'Platform\\Planner\\Models\\PlannerProject';
        }
        if (Schema::hasTable('planner_tasks')) {
            $coreModels[] = 'Platform\\Planner\\Models\\PlannerTask';
        }
        if (Schema::hasTable('planner_sprints')) {
            $coreModels[] = 'Platform\\Planner\\Models\\PlannerSprint';
        }
        
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