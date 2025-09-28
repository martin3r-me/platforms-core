<?php

namespace Platform\Core\Tools;

class CoreHelpTool
{
    public function getHelp(): array
    {
        return [
            'ok' => true,
            'data' => [
                'help' => [
                    'core_tools' => [
                        'get_current_time' => 'Aktuelle Serverzeit abrufen',
                        'get_context' => 'Aktuellen User/Team Kontext abrufen',
                        'get_modules' => 'Verfügbare Module und deren Models anzeigen',
                        'get_help' => 'Diese Hilfe anzeigen',
                        'discover_tools' => 'Tools für spezifisches Modul entdecken'
                    ],
                    'usage_examples' => [
                        'discover_tools("planner")' => 'Zeigt alle Planner-Tools',
                        'discover_tools("okr")' => 'Zeigt alle OKR-Tools',
                        'get_modules()' => 'Zeigt alle verfügbaren Module'
                    ],
                    'workflow' => [
                        '1. Verwende get_modules() um Module zu sehen',
                        '2. Verwende discover_tools("modul") um spezifische Tools zu finden',
                        '3. Führe dann die gefundenen Tools aus'
                    ]
                ]
            ],
            'message' => 'Hilfe und Anleitung geladen'
        ];
    }
}
