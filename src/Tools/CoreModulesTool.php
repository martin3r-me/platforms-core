<?php

namespace Platform\Core\Tools;

class CoreModulesTool
{
    public function getModules(): array
    {
        return [
            'ok' => true,
            'data' => [
                'modules' => [
                    'planner' => [
                        'name' => 'Projekt-Management',
                        'description' => 'Projekte, Tasks und Project Slots verwalten',
                        'models' => ['PlannerProject', 'PlannerTask', 'PlannerProjectSlot']
                    ],
                    'okr' => [
                        'name' => 'Objectives and Key Results',
                        'description' => 'OKR-Zyklen, Objectives und Key Results verwalten',
                        'models' => ['Okr', 'Cycle', 'Objective', 'KeyResult', 'KeyResultPerformance']
                    ],
                    'crm' => [
                        'name' => 'Customer Relationship Management',
                        'description' => 'Kunden und Kontakte verwalten',
                        'models' => ['Contact', 'Company', 'Deal']
                    ],
                    'core' => [
                        'name' => 'Kern-Funktionen',
                        'description' => 'Basis-Funktionen und System-Tools',
                        'models' => ['User', 'Team']
                    ]
                ]
            ],
            'message' => 'Verfügbare Module geladen'
        ];
    }
}
