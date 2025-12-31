# Tool-Registrierung in Modulen

## Loose Coupling - Module können beliebig viele Tools registrieren

Das System unterstützt **zwei Wege** für die Tool-Registrierung:

### 1. Auto-Discovery (Empfohlen) ✅

**Einfachste Methode**: Lege Tools in `modules/{module}/src/Tools/` ab.

**Beispiel für Planner:**
```
modules/planner/src/Tools/
  ├── CreateProjectTool.php
  ├── UpdateProjectTool.php
  ├── DeleteProjectTool.php
  ├── ListProjectsTool.php
  ├── CreateTaskTool.php
  ├── UpdateTaskTool.php
  └── ... (beliebig viele Tools)
```

**Vorteile:**
- ✅ Keine manuelle Registrierung nötig
- ✅ Automatisch gefunden und registriert
- ✅ Funktioniert rekursiv (Unterverzeichnisse werden durchsucht)
- ✅ Loose gekoppelt - Core weiß nichts von Modul-Tools

**Tool-Struktur:**
```php
<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

class CreateProjectTool implements ToolContract
{
    public function getName(): string
    {
        return 'planner.projects.create'; // WICHTIG: Mit Modul-Präfix!
    }

    public function getDescription(): string
    {
        return 'Erstellt ein neues Projekt...';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => '...'],
                // ...
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        // Tool-Logik hier
        return ToolResult::success(['project_id' => 123]);
    }
}
```

### 2. Manuelle Registrierung (Optional)

Falls du komplexe Initialisierung brauchst, kannst du Tools auch manuell registrieren:

**In `PlannerServiceProvider::boot()`:**
```php
protected function registerTools(): void
{
    try {
        $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);
        
        // Tools manuell registrieren
        $registry->register(new \Platform\Planner\Tools\CreateProjectTool());
        $registry->register(new \Platform\Planner\Tools\UpdateProjectTool());
        // ... beliebig viele Tools
    } catch (\Throwable $e) {
        \Log::warning('Planner: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
    }
}
```

**Vorteile:**
- ✅ Volle Kontrolle über Registrierungs-Zeitpunkt
- ✅ Kann Dependencies injizieren
- ✅ Kann Conditional Registration machen

### Best Practices

1. **Tool-Namen**: Verwende immer Modul-Präfix (`planner.projects.create`, nicht nur `projects.create`)
2. **Dependencies**: Nutze `ToolDependencyContract` für automatisches Tool-Chaining
3. **Metadata**: Nutze `ToolMetadataContract` für bessere Discovery
4. **Testing**: Nutze `ToolTestCase` für Tool-Tests

### Beispiel: Neues Tool hinzufügen

**Schritt 1**: Erstelle Tool-Datei
```bash
php artisan make:tool planner.tasks.create --module=planner --description="Erstellt eine neue Aufgabe"
```

**Schritt 2**: Implementiere Tool-Logik
```php
// modules/planner/src/Tools/CreateTaskTool.php
class CreateTaskTool implements ToolContract
{
    // ... implementiere ToolContract
}
```

**Schritt 3**: Fertig! ✅
- Tool wird automatisch gefunden und registriert
- Keine weitere Konfiguration nötig
- Sofort verfügbar für AI/Chat

### Tool-Generator

Nutze den Tool-Generator für schnelle Erstellung:
```bash
php artisan make:tool planner.projects.update \
    --module=planner \
    --description="Aktualisiert ein bestehendes Projekt" \
    --dependencies \
    --metadata
```

Das System ist **vollständig loose gekoppelt** - Module können beliebig viele Tools registrieren, ohne dass Core etwas davon weiß! 🚀

