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
            
            return [
                'ok' => false,
                'error' => "Tool '$toolName' nicht gefunden"
            ];
            
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage()
            ];
        }
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
            'Plannertask' => 'Platform\\Planner\\Models\\PlannerTask',
            'Plannersprint' => 'Platform\\Planner\\Models\\PlannerSprint',
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
}