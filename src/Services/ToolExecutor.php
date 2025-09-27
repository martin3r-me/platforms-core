<?php

namespace Platform\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ToolExecutor
{
    protected ToolRegistry $toolRegistry;
    
    public function __construct(ToolRegistry $toolRegistry)
    {
        $this->toolRegistry = $toolRegistry;
    }
    
    /**
     * Führe ein Tool aus
     */
    public function executeTool(string $toolName, array $parameters): array
    {
        try {
            // Input Validation
            if (empty($toolName)) {
                return $this->createErrorResponse('Tool name is required');
            }
            
            if (!is_array($parameters)) {
                return $this->createErrorResponse('Parameters must be an array');
            }
            
            \Log::info("🔧 EXECUTING TOOL:", [
                'tool' => $toolName,
                'parameters' => $parameters
            ]);
            
            // Core Tools
            if (in_array($toolName, ['get_current_time', 'get_context'])) {
                return $this->executeCoreTool($toolName, $parameters);
            }
            
            // Dynamische Model-Tools
            if (str_contains($toolName, '_get_all')) {
                return $this->executeGetAll($toolName, $parameters);
            }
            
            if (str_contains($toolName, '_create')) {
                return $this->executeCreate($toolName, $parameters);
            }
            
            if (str_contains($toolName, '_update')) {
                return $this->executeUpdate($toolName, $parameters);
            }
            
            if (str_contains($toolName, '_delete')) {
                return $this->executeDelete($toolName, $parameters);
            }
            
            // Relation Tools (LOOSE!)
            if (str_contains($toolName, '_') && !str_contains($toolName, '_get_all') && 
                !str_contains($toolName, '_create') && !str_contains($toolName, '_update') && 
                !str_contains($toolName, '_delete') && !str_contains($toolName, '_values')) {
                return $this->executeRelation($toolName, $parameters);
            }
            
            // Enum Tools (LOOSE!)
            if (str_contains($toolName, '_values')) {
                return $this->executeEnumValues($toolName, $parameters);
            }
            
            return $this->createErrorResponse("Tool '$toolName' nicht gefunden");
            
        } catch (\Exception $e) {
            \Log::error("🔧 TOOL EXECUTION ERROR:", [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->createErrorResponse("Tool execution failed: " . $e->getMessage());
        } catch (\Throwable $e) {
            \Log::error("🔧 TOOL EXECUTION CRITICAL ERROR:", [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->createErrorResponse("Critical tool execution error: " . $e->getMessage());
        }
    }
    
    /**
     * Erstelle standardisierte Error Response
     */
    protected function createErrorResponse(string $message, array $data = []): array
    {
        return [
            'ok' => false,
            'error' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];
    }
    
    /**
     * Core Tools
     */
    protected function executeCoreTool(string $toolName, array $parameters): array
    {
        switch ($toolName) {
            case 'get_current_time':
                return [
                    'ok' => true,
                    'data' => [
                        'time' => now()->format('Y-m-d H:i:s'),
                        'timezone' => config('app.timezone')
                    ]
                ];
                
            case 'get_context':
                $user = auth()->user();
                return [
                    'ok' => true,
                    'data' => [
                        'user_id' => $user?->id,
                        'user_name' => $user?->name,
                        'team_id' => $user?->currentTeam?->id,
                        'team_name' => $user?->currentTeam?->name,
                        'current_time' => now()->format('Y-m-d H:i:s')
                    ]
                ];
        }
    }
    
    /**
     * Führe GET ALL aus
     */
    protected function executeGetAll(string $toolName, array $parameters): array
    {
        $modelClass = $this->getModelClassFromToolName($toolName);
        
        if (!$modelClass || !class_exists($modelClass)) {
            return [
                'ok' => false,
                'error' => "Model für Tool '$toolName' nicht gefunden"
            ];
        }
        
        $query = $modelClass::query();
        
        // Filter anwenden
        foreach ($parameters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }
        
        $results = $query->limit(50)->get();
        
        // DEBUG: Logge die Ergebnisse
        \Log::info("🔧 TOOL EXECUTOR GET_ALL:", [
            'tool' => $toolName,
            'model' => $modelClass,
            'count' => $results->count(),
            'items' => $results->toArray()
        ]);
        
        return [
            'ok' => true,
            'data' => [
                'items' => $results->toArray(),
                'count' => $results->count()
            ]
        ];
    }
    
    /**
     * Führe CREATE aus
     */
    protected function executeCreate(string $toolName, array $parameters): array
    {
        $modelClass = $this->getModelClassFromToolName($toolName);
        
        if (!$modelClass || !class_exists($modelClass)) {
            return [
                'ok' => false,
                'error' => "Model für Tool '$toolName' nicht gefunden"
            ];
        }
        
        try {
            $model = new $modelClass();
            $model->fill($parameters);
            $model->save();
            
            return [
                'ok' => true,
                'data' => [
                    'id' => $model->id,
                    'item' => $model->toArray(),
                    'message' => "Eintrag erfolgreich erstellt"
                ]
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => "Fehler beim Erstellen: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Führe UPDATE aus
     */
    protected function executeUpdate(string $toolName, array $parameters): array
    {
        $modelClass = $this->getModelClassFromToolName($toolName);
        
        if (!$modelClass || !class_exists($modelClass)) {
            return [
                'ok' => false,
                'error' => "Model für Tool '$toolName' nicht gefunden"
            ];
        }
        
        $id = $parameters['id'] ?? null;
        if (!$id) {
            return [
                'ok' => false,
                'error' => "ID ist erforderlich für Update"
            ];
        }
        
        try {
            $model = $modelClass::findOrFail($id);
            
            // ID aus Parametern entfernen
            unset($parameters['id']);
            
            $model->fill($parameters);
            $model->save();
            
            return [
                'ok' => true,
                'data' => [
                    'id' => $model->id,
                    'item' => $model->toArray(),
                    'message' => "Eintrag erfolgreich aktualisiert"
                ]
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => "Fehler beim Aktualisieren: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Führe DELETE aus
     */
    protected function executeDelete(string $toolName, array $parameters): array
    {
        $modelClass = $this->getModelClassFromToolName($toolName);
        
        if (!$modelClass || !class_exists($modelClass)) {
            return [
                'ok' => false,
                'error' => "Model für Tool '$toolName' nicht gefunden"
            ];
        }
        
        $id = $parameters['id'] ?? null;
        if (!$id) {
            return [
                'ok' => false,
                'error' => "ID ist erforderlich für Delete"
            ];
        }
        
        try {
            $model = $modelClass::findOrFail($id);
            $model->delete();
            
            return [
                'ok' => true,
                'data' => [
                    'id' => $id,
                    'message' => "Eintrag erfolgreich gelöscht"
                ]
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => "Fehler beim Löschen: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Hole Model-Class aus Tool-Name
     */
    protected function getModelClassFromToolName(string $toolName): ?string
    {
        // Tool-Name: plannerproject_get_all -> Model: PlannerProject
        $parts = explode('_', $toolName);
        $modelName = ucfirst($parts[0]);
        
        // Mapping für bekannte Models
        $modelMapping = [
            'Plannerproject' => 'Platform\\Planner\\Models\\PlannerProject',
            'Plannerprojectslot' => 'Platform\\Planner\\Models\\PlannerProjectSlot',
            'Plannertask' => 'Platform\\Planner\\Models\\PlannerTask',
            'Plannersprint' => 'Platform\\Planner\\Models\\PlannerSprint',
            'Plannersprintslot' => 'Platform\\Planner\\Models\\PlannerSprintSlot',
            'Plannertaskgroup' => 'Platform\\Planner\\Models\\PlannerTaskGroup',
            'Plannerprojectuser' => 'Platform\\Planner\\Models\\PlannerProjectUser',
            'Plannercustomerproject' => 'Platform\\Planner\\Models\\PlannerCustomerProject',
            'Plannercustomerprojectbillingitem' => 'Platform\\Planner\\Models\\PlannerCustomerProjectBillingItem',
            'Plannercustomerprojectparty' => 'Platform\\Planner\\Models\\PlannerCustomerProjectParty',
            'Crmcontact' => 'Platform\\Crm\\Models\\CrmContact',
            'Crmcompany' => 'Platform\\Crm\\Models\\CrmCompany',
            'Okrobjective' => 'Platform\\Okr\\Models\\OkrObjective',
            'Okrkeyresult' => 'Platform\\Okr\\Models\\OkrKeyResult',
            'Corechat' => 'Platform\\Core\\Models\\CoreChat',
            'Corechatmessage' => 'Platform\\Core\\Models\\CoreChatMessage',
            'Team' => 'Platform\\Core\\Models\\Team',
            'User' => 'Platform\\Core\\Models\\User',
        ];
        
        return $modelMapping[$modelName] ?? null;
    }
    
    /**
     * Führe Relation Tool aus (LOOSE!)
     */
    protected function executeRelation(string $toolName, array $parameters): array
    {
        try {
            // Tool-Name: plannerproject_tasks -> Model: PlannerProject, Relation: tasks
            $parts = explode('_', $toolName);
            $modelName = ucfirst($parts[0]);
            $relationName = $parts[1];
            
            // Model finden
            $modelClass = $this->getModelClassFromToolName($toolName);
            if (!$modelClass) {
                return ['ok' => false, 'error' => "Model für Tool '$toolName' nicht gefunden"];
            }
            
            // Model laden
            $model = $modelClass::find($parameters['id']);
            if (!$model) {
                return ['ok' => false, 'error' => "Model mit ID {$parameters['id']} nicht gefunden"];
            }
            
            // Relation ausführen
            $relation = $model->$relationName();
            $items = $relation->get();
            
            return [
                'ok' => true,
                'data' => $items->toArray(),
                'count' => $items->count(),
                'relation' => $relationName
            ];
            
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => "Relation Fehler: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Führe Enum Values Tool aus (LOOSE!)
     */
    protected function executeEnumValues(string $toolName, array $parameters): array
    {
        try {
            // Tool-Name: plannerproject_status_values -> Model: PlannerProject, Enum: status
            $parts = explode('_', $toolName);
            $modelName = ucfirst($parts[0]);
            $enumName = $parts[1];
            
            // Model finden
            $modelClass = $this->getModelClassFromToolName($toolName);
            if (!$modelClass) {
                return ['ok' => false, 'error' => "Model für Tool '$toolName' nicht gefunden"];
            }
            
            // Enum-Werte finden
            $enumValues = $this->getEnumValues($enumName, $modelClass);
            
            return [
                'ok' => true,
                'data' => $enumValues,
                'enum' => $enumName
            ];
            
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => "Enum Fehler: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Hole Enum-Werte für ein Model
     */
    protected function getEnumValues(string $enumName, string $modelClass): array
    {
        try {
            $model = new $modelClass();
            $reflection = new \ReflectionClass($model);
            
            // Suche nach Enum-Property
            foreach ($reflection->getProperties() as $property) {
                if ($property->getName() === $enumName) {
                    $type = $property->getType();
                    if ($type && $type instanceof \ReflectionNamedType) {
                        $enumClass = $type->getName();
                        if (class_exists($enumClass) && is_subclass_of($enumClass, 'BackedEnum')) {
                            return array_map(fn($case) => $case->value, $enumClass::cases());
                        }
                    }
                }
            }
            
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }
}