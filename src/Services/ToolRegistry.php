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
                \Log::info("🔧 GENERATING TOOLS:", ['timestamp' => now()]);
                $startTime = microtime(true);
                
                $tools = $this->generateTools();
                
                $endTime = microtime(true);
                $executionTime = ($endTime - $startTime) * 1000; // in ms
                
                \Log::info("🔧 TOOLS GENERATED:", [
                    'count' => count($tools),
                    'execution_time_ms' => round($executionTime, 2)
                ]);
                
                return $tools;
            });
        }
        
        return $this->cachedTools;
    }
    
    /**
     * Lade Tools für spezifisches Modul
     */
    public function getToolsForModule(string $module): array
    {
        $allTools = $this->getAllTools();
        $moduleTools = [];
        
        foreach ($allTools as $tool) {
            $toolName = $tool['function']['name'] ?? '';
            
            // Filtere nach Modul
            if (str_starts_with($toolName, $module)) {
                $moduleTools[] = $tool;
            }
        }
        
        \Log::info("🔧 MODULE TOOLS:", [
            'module' => $module,
            'count' => count($moduleTools)
        ]);
        
        return $moduleTools;
    }
    
    /**
     * Lade Tools basierend auf Query-Kontext
     */
    public function getContextualTools(string $query): array
    {
        $allTools = $this->getAllTools();
        $contextualTools = [];
        
        // Analysiere Query für relevante Module
        $relevantModules = $this->detectRelevantModules($query);
        
        foreach ($allTools as $tool) {
            $toolName = $tool['function']['name'] ?? '';
            
            // Prüfe ob Tool zu relevanten Modulen gehört
            foreach ($relevantModules as $module) {
                if (str_starts_with($toolName, $module)) {
                    $contextualTools[] = $tool;
                    break;
                }
            }
        }
        
        // Fallback: Core Tools immer hinzufügen
        $coreTools = $this->getCoreTools();
        $contextualTools = array_merge($coreTools, $contextualTools);
        
        \Log::info("🔧 CONTEXTUAL TOOLS:", [
            'query' => $query,
            'modules' => $relevantModules,
            'count' => count($contextualTools)
        ]);
        
        return $contextualTools;
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
            
            // Relation Tools generieren (LOOSE!)
            $relationTools = $this->generateRelationTools($modelClass, $modelName);
            $tools = array_merge($tools, $relationTools);
            
            // Enum Tools generieren (LOOSE!)
            $enumTools = $this->generateEnumTools($modelClass, $modelName);
            $tools = array_merge($tools, $enumTools);
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
        if (Schema::hasTable('okr_cycles')) {
            $coreModels[] = 'Platform\\Okr\\Models\\Cycle';
        }
        if (Schema::hasTable('okr_objectives')) {
            $coreModels[] = 'Platform\\Okr\\Models\\Objective';
        }
        if (Schema::hasTable('okr_key_results')) {
            $coreModels[] = 'Platform\\Okr\\Models\\KeyResult';
        }
        if (Schema::hasTable('okr_okrs')) {
            $coreModels[] = 'Platform\\Okr\\Models\\Okr';
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
        
        // Model-Dokumentation aus DocComment (LOOSE!)
        $documentation = $this->getModelDocumentation($modelClass);
        
        // Beschreibung aus DocComment
        $description = "Alle {$modelName} Einträge abrufen";
        if (!empty($documentation['description'])) {
            $description = $documentation['description'];
        }
        
        // Intelligente Beschreibungen basierend auf Model-Name
        if (str_contains($modelName, 'ProjectSlot')) {
            $description = "Project Slots abrufen - Container für Tasks in Projekten";
        } elseif (str_contains($modelName, 'Task')) {
            $description = "Tasks abrufen - Aufgaben und To-Dos";
        } elseif (str_contains($modelName, 'Project')) {
            $description = "Projekte abrufen - Projekt-Management";
        }
        
        // Hints hinzufügen
        if (!empty($documentation['hints'])) {
            $description .= " - Hints: " . implode(', ', $documentation['hints']);
        }
        
        return [
            'type' => 'function',
            'function' => [
                'name' => strtolower($modelName) . '_get_all',
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => []
                ]
            ]
        ];
    }
    
    /**
     * Intelligente Tool-Beschreibungen (LOOSE!)
     */
    protected function getIntelligentDescription(string $modelName): string
    {
        // Fallback: Standard-Beschreibung
        return "Alle {$modelName} Einträge abrufen";
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
        
        // Intelligente Beschreibungen für CREATE Tools
        $description = "Neuen {$modelName} erstellen";
        if (str_contains($modelName, 'ProjectSlot')) {
            $description = "Project Slot erstellen - Container für Tasks in Projekten";
        } elseif (str_contains($modelName, 'Task')) {
            $description = "Task erstellen - Neue Aufgabe anlegen";
        } elseif (str_contains($modelName, 'Project')) {
            $description = "Projekt erstellen - Neues Projekt anlegen";
        }
        
        return [
            'type' => 'function',
            'function' => [
                'name' => strtolower($modelName) . '_create',
                'description' => $description,
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
     * Hole Model-Dokumentation (LOOSE!)
     */
    protected function getModelDocumentation(string $modelClass): array
    {
        $documentation = [];
        
        try {
            $reflection = new \ReflectionClass($modelClass);
            
            // DocComment auslesen
            $docComment = $reflection->getDocComment();
            if ($docComment) {
                $documentation['description'] = $this->extractDescriptionFromDocComment($docComment);
                $documentation['hints'] = $this->extractHintsFromDocComment($docComment);
            }
            
            // Laravel-Konventionen erkennen
            $documentation['conventions'] = $this->detectLaravelConventions($modelClass);
            
            // Business-Logic-Hints
            $documentation['business_logic'] = $this->detectBusinessLogic($modelClass);
            
        } catch (\Exception $e) {
            \Log::warning("🔍 DOCUMENTATION FAILED:", ['model' => $modelClass, 'error' => $e->getMessage()]);
        }
        
        return $documentation;
    }
    
    /**
     * Extrahiere Beschreibung aus DocComment
     */
    protected function extractDescriptionFromDocComment(string $docComment): string
    {
        $lines = explode("\n", $docComment);
        $description = '';
        
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*");
            if (str_starts_with($line, '@') || empty($line)) {
                continue;
            }
            $description .= $line . ' ';
        }
        
        return trim($description);
    }
    
    /**
     * Extrahiere Hints aus DocComment
     */
    protected function extractHintsFromDocComment(string $docComment): array
    {
        $hints = [];
        $lines = explode("\n", $docComment);
        
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*");
            if (str_starts_with($line, '@hint')) {
                $hints[] = trim(substr($line, 5));
            }
        }
        
        return $hints;
    }
    
    /**
     * Erkenne Laravel-Konventionen
     */
    protected function detectLaravelConventions(string $modelClass): array
    {
        $conventions = [];
        
        try {
            $model = new $modelClass();
            $reflection = new \ReflectionClass($model);
            
            // Table-Name Konvention
            $conventions['table'] = $model->getTable();
            
            // Primary Key Konvention
            $conventions['primary_key'] = $model->getKeyName();
            
            // Timestamps Konvention
            $conventions['timestamps'] = $model->usesTimestamps();
            
            // Fillable Konvention
            $conventions['fillable'] = $model->getFillable();
            
            // Relations Konvention
            $conventions['relations'] = $this->detectRelationConventions($reflection);
            
        } catch (\Exception $e) {
            \Log::warning("🔍 CONVENTION DETECTION FAILED:", ['model' => $modelClass, 'error' => $e->getMessage()]);
        }
        
        return $conventions;
    }
    
    /**
     * Erkenne Relation-Konventionen
     */
    protected function detectRelationConventions(\ReflectionClass $reflection): array
    {
        $relations = [];
        
        foreach ($reflection->getMethods() as $method) {
            $methodName = $method->getName();
            
            // Laravel Relation Patterns
            if (preg_match('/^(hasOne|hasMany|belongsTo|belongsToMany|morphTo|morphMany|morphOne)$/', $methodName)) {
                $relations[] = [
                    'name' => $methodName,
                    'type' => $this->detectRelationType($methodName),
                    'convention' => 'Laravel Standard'
                ];
            }
        }
        
        return $relations;
    }
    
    /**
     * Erkenne Business-Logic
     */
    protected function detectBusinessLogic(string $modelClass): array
    {
        $businessLogic = [];
        
        try {
            $reflection = new \ReflectionClass($modelClass);
            
            // Scopes erkennen
            $scopes = [];
            foreach ($reflection->getMethods() as $method) {
                if (str_starts_with($method->getName(), 'scope')) {
                    $scopes[] = $method->getName();
                }
            }
            if (!empty($scopes)) {
                $businessLogic['scopes'] = $scopes;
            }
            
            // Accessors/Mutators erkennen
            $accessors = [];
            foreach ($reflection->getMethods() as $method) {
                if (str_starts_with($method->getName(), 'get') && str_ends_with($method->getName(), 'Attribute')) {
                    $accessors[] = $method->getName();
                }
            }
            if (!empty($accessors)) {
                $businessLogic['accessors'] = $accessors;
            }
            
        } catch (\Exception $e) {
            \Log::warning("🔍 BUSINESS LOGIC DETECTION FAILED:", ['model' => $modelClass, 'error' => $e->getMessage()]);
        }
        
        return $businessLogic;
    }
    
    /**
     * Generiere Relation Tools für ein Model (LOOSE!)
     */
    protected function generateRelationTools(string $modelClass, string $modelName): array
    {
        $tools = [];
        $relations = $this->discoverModelRelations($modelClass);
        
        foreach ($relations as $relation) {
            $relationName = $relation['name'];
            $relationType = $relation['type'];
            
            // Relation-Beschreibung aus Model-Dokumentation (LOOSE!)
            $description = $this->getRelationDescriptionFromModel($modelClass, $relationName, $relationType);
            
            // Generiere Tool für jede Relation
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => strtolower($modelName) . '_' . $relationName,
                    'description' => $description,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                                'description' => "ID des {$modelName}"
                            ]
                        ],
                        'required' => ['id']
                    ]
                ]
            ];
        }
        
        return $tools;
    }
    
    /**
     * Relation-Beschreibung aus Model-Dokumentation (LOOSE!)
     */
    protected function getRelationDescriptionFromModel(string $modelClass, string $relationName, string $relationType): string
    {
        try {
            $reflection = new \ReflectionClass($modelClass);
            $method = $reflection->getMethod($relationName);
            $docComment = $method->getDocComment();
            
            if ($docComment) {
                // Extrahiere Beschreibung aus DocComment
                $lines = explode("\n", $docComment);
                foreach ($lines as $line) {
                    $line = trim($line, " \t\n\r\0\x0B*");
                    if (str_starts_with($line, '@hint')) {
                        return trim(substr($line, 5));
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning("🔍 RELATION DESCRIPTION FAILED:", ['model' => $modelClass, 'relation' => $relationName, 'error' => $e->getMessage()]);
        }
        
        // Fallback: Standard-Beschreibung
        return "{$relationName} Relation ({$relationType})";
    }
    
    /**
     * Generiere Enum Tools für ein Model (LOOSE!)
     */
    protected function generateEnumTools(string $modelClass, string $modelName): array
    {
        $tools = [];
        $enums = $this->discoverModelEnums($modelClass);
        
        foreach ($enums as $enum) {
            $enumName = $enum['name'];
            $enumValues = $enum['values'];
            
            // Generiere Tool für jeden Enum
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => strtolower($modelName) . '_' . strtolower($enumName) . '_values',
                    'description' => "Verfügbare Werte für {$enumName} in {$modelName}",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[],
                        'required' => []
                    ]
                ]
            ];
        }
        
        return $tools;
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
     * Entdecke alle Models LOOSE - nur über Composer Autoload (Performance optimiert)
     */
    protected function discoverAllModels(): array
    {
        // Cache für Model Discovery
        return Cache::remember('discovered_models', 3600, function() {
            $models = [];
            $startTime = microtime(true);
            
            try {
                // Loose: Nur über Composer Autoload alle Klassen scannen
                $autoloader = require base_path('vendor/autoload.php');
                $classMap = $autoloader->getClassMap();
                
                if (empty($classMap)) {
                    \Log::warning("🔍 EMPTY CLASS MAP:", ['message' => 'Composer autoloader returned empty class map']);
                    return $this->getFallbackModels();
                }
                
                // Performance: Filtere vorher nach Platform Namespace
                $platformClasses = array_filter($classMap, function($className) {
                    return str_starts_with($className, 'Platform\\');
                }, ARRAY_FILTER_USE_KEY);
                
                foreach ($platformClasses as $className => $filePath) {
                    try {
                        // Nur Models (endet mit Model oder ist in Models Namespace)
                        if (!str_ends_with($className, 'Model') && !str_contains($className, '\\Models\\')) {
                            continue;
                        }
                        
                        // Ausschluss nur von echten Support Models (nicht Performance!)
                        if (str_contains($className, 'Template') ||
                            str_contains($className, 'Party') ||
                            str_contains($className, 'Billing')) {
                            continue;
                        }
                        
                        // Prüfe ob es ein Eloquent Model ist
                        if (class_exists($className) && is_subclass_of($className, \Illuminate\Database\Eloquent\Model::class)) {
                            $models[] = $className;
                        }
                    } catch (\Exception $e) {
                        \Log::warning("🔍 MODEL DISCOVERY FAILED:", [
                            'model' => $className,
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }
                
                if (empty($models)) {
                    \Log::warning("🔍 NO MODELS FOUND:", ['message' => 'No models discovered, using fallback']);
                    return $this->getFallbackModels();
                }
                
                $endTime = microtime(true);
                $executionTime = ($endTime - $startTime) * 1000; // in ms
                
                \Log::info("🔍 MODELS DISCOVERED:", [
                    'count' => count($models),
                    'execution_time_ms' => round($executionTime, 2)
                ]);
                
            } catch (\Exception $e) {
                \Log::error("🔍 MODEL DISCOVERY CRITICAL ERROR:", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->getFallbackModels();
            }
            
            return $models;
        });
    }
    
    /**
     * Fallback Models wenn Discovery fehlschlägt
     */
    protected function getFallbackModels(): array
    {
        $fallbackModels = [
            'Platform\\Planner\\Models\\PlannerProject',
            'Platform\\Planner\\Models\\PlannerTask',
            'Platform\\Core\\Models\\User',
            'Platform\\Core\\Models\\Team',
        ];
        
        $validModels = [];
        foreach ($fallbackModels as $modelClass) {
            if (class_exists($modelClass)) {
                $validModels[] = $modelClass;
            }
        }
        
        \Log::info("🔍 FALLBACK MODELS:", ['models' => $validModels]);
        return $validModels;
    }
    
    /**
     * Entdecke alle Relations für ein Model (LOOSE!)
     */
    protected function discoverModelRelations(string $modelClass): array
    {
        $relations = [];
        
        try {
            $model = new $modelClass();
            $reflection = new \ReflectionClass($model);
            
            // Alle Methoden durchgehen
            foreach ($reflection->getMethods() as $method) {
                $methodName = $method->getName();
                
                // Relations haben typischerweise diese Patterns
                if (preg_match('/^(hasOne|hasMany|belongsTo|belongsToMany|morphTo|morphMany|morphOne)$/', $methodName) ||
                    str_contains($methodName, 'Relation') ||
                    str_contains($methodName, 'Through')) {
                    
                    $relations[] = [
                        'name' => $methodName,
                        'type' => $this->detectRelationType($methodName),
                        'description' => "Relation: {$methodName}"
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning("🔍 RELATION DISCOVERY FAILED:", ['model' => $modelClass, 'error' => $e->getMessage()]);
        }
        
        return $relations;
    }
    
    /**
     * Entdecke alle Enums für ein Model (LOOSE!)
     */
    protected function discoverModelEnums(string $modelClass): array
    {
        $enums = [];
        
        try {
            $model = new $modelClass();
            $reflection = new \ReflectionClass($model);
            
            // Alle Properties durchgehen
            foreach ($reflection->getProperties() as $property) {
                $propertyName = $property->getName();
                
                // Prüfe ob es ein Enum ist
                if (class_exists($propertyName) && is_subclass_of($propertyName, 'BackedEnum')) {
                    $enums[] = [
                        'name' => $propertyName,
                        'values' => $this->getEnumValues($propertyName),
                        'description' => "Enum: {$propertyName}"
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning("🔍 ENUM DISCOVERY FAILED:", ['model' => $modelClass, 'error' => $e->getMessage()]);
        }
        
        return $enums;
    }
    
    /**
     * Erkenne Relation Type
     */
    protected function detectRelationType(string $methodName): string
    {
        if (str_contains($methodName, 'hasOne')) return 'hasOne';
        if (str_contains($methodName, 'hasMany')) return 'hasMany';
        if (str_contains($methodName, 'belongsTo')) return 'belongsTo';
        if (str_contains($methodName, 'belongsToMany')) return 'belongsToMany';
        if (str_contains($methodName, 'morphTo')) return 'morphTo';
        if (str_contains($methodName, 'morphMany')) return 'morphMany';
        if (str_contains($methodName, 'morphOne')) return 'morphOne';
        
        return 'unknown';
    }
    
    /**
     * Filtere wichtige Tools (OpenAI Limit)
     */
    protected function filterImportantTools(array $tools, int $limit): array
    {
        // LOOSE: Dynamische Prioritäten basierend auf Modul-Importanz
        $priorities = [
            // Höchste Priorität: Planner (wichtigstes Modul)
            'planner' => 10,
            // CRM und OKR gleich wichtig
            'crm' => 8,
            'okr' => 8,
            // Core Tools
            'core' => 4,
        ];
        
        // LOOSE: Gruppiere Tools nach Modul (nicht nach Model)
        $groupedTools = [];
        foreach ($tools as $tool) {
            $toolName = $tool['function']['name'] ?? '';
            $modelName = explode('_', $toolName)[0] ?? '';
            
            // Bestimme Modul basierend auf Model-Name
            $module = $this->getModuleFromModelName($modelName);
            $groupedTools[$module][] = $tool;
        }
        
        // Sortiere Module nach Priorität
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
     * Bestimme Modul basierend auf Model-Name (LOOSE!)
     */
    protected function getModuleFromModelName(string $modelName): string
    {
        // Konvertiere zu lowercase für Vergleich
        $name = strtolower($modelName);
        
        // Bestimme Modul basierend auf Prefix
        if (str_starts_with($name, 'planner')) {
            return 'planner';
        }
        if (str_starts_with($name, 'crm')) {
            return 'crm';
        }
        if (str_starts_with($name, 'okr')) {
            return 'okr';
        }
        if (str_starts_with($name, 'core')) {
            return 'core';
        }
        
        // Fallback: core
        return 'core';
    }
    
    /**
     * Erkenne relevante Module basierend auf Query
     */
    protected function detectRelevantModules(string $query): array
    {
        $query = strtolower($query);
        $modules = [];
        
        // Planner Module
        if (str_contains($query, 'projekt') || str_contains($query, 'aufgabe') || 
            str_contains($query, 'task') || str_contains($query, 'sprint') ||
            str_contains($query, 'slot') || str_contains($query, 'planning') ||
            str_contains($query, 'fällig') || str_contains($query, 'due') ||
            str_contains($query, 'deadline') || str_contains($query, 'termin')) {
            $modules[] = 'planner';
        }
        
        // Spezielle Mapping für "slots" → "projectslots"
        if (str_contains($query, 'slot')) {
            $modules[] = 'plannerprojectslot'; // Slots sind Project Slots
        }
        
        // Spezielle Mapping für "aufgaben" → "tasks"
        if (str_contains($query, 'aufgabe') || str_contains($query, 'task')) {
            $modules[] = 'plannertask'; // Aufgaben sind Tasks
        }
        
        // Spezielle Mapping für "projekte" → "projects"
        if (str_contains($query, 'projekt')) {
            $modules[] = 'plannerproject'; // Projekte sind Projects
        }
        
        // OKR Module
        if (str_contains($query, 'okr') || str_contains($query, 'objective') || str_contains($query, 'ziel')) {
            $modules[] = 'okrok';
            $modules[] = 'okrobjective';
        }
        if (str_contains($query, 'cycle') || str_contains($query, 'zyklus')) {
            $modules[] = 'okrcycle';
        }
        if (str_contains($query, 'key result') || str_contains($query, 'keyresult')) {
            $modules[] = 'okrkeyresult';
        }
        
        // CRM Module
        if (str_contains($query, 'kontakt') || str_contains($query, 'kunde') || 
            str_contains($query, 'company') || str_contains($query, 'lead') ||
            str_contains($query, 'crm')) {
            $modules[] = 'crm';
        }
        
        // OKR Module
        if (str_contains($query, 'okr') || str_contains($query, 'objective') || 
            str_contains($query, 'key result') || str_contains($query, 'ziel')) {
            $modules[] = 'okr';
        }
        
        // CMS Module
        if (str_contains($query, 'content') || str_contains($query, 'cms') || 
            str_contains($query, 'board') || str_contains($query, 'page')) {
            $modules[] = 'cms';
        }
        
        // Core Module (immer relevant)
        $modules[] = 'core';
        
        return array_unique($modules);
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