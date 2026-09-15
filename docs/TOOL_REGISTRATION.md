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

### Tag-Konventionen für Discovery (Kanal- & Handlungs-Tag-Vokabular)

Der `ToolMetadataResolver` leitet Tags automatisch aus den Namens-Segmenten ab (z. B. `terminal.channels.GET`
→ `["terminal", "channels"]`). Das reicht nicht für zuverlässige Registry-Suche über Tools hinweg, die
unterschiedlich benannt sind, aber fachlich zusammengehören (z. B. `terminal.channels.GET`,
`user-connectors.microsoft365.mail.list` und `core.comms.wa_overview.GET` sind alle "Kommunikations-Tools",
tragen das aber nicht konsistent im Namen).

Für **alle Kommunikations-Tools** (Terminal, E-Mail, Microsoft Teams, WhatsApp/`core.comms.*`, künftige Kanäle)
gilt deshalb zusätzlich zu den auto-derived Tags ein festes, additives Vokabular über `getMetadata()['tags']`
(wird mit den Auto-Tags gemergt, siehe `ToolMetadataResolver::applyExplicitMetadata()`):

**Kanal-Tags** (`channel:*`, ein Tool trägt in der Regel genau einen):
- `channel:terminal` — internes Messaging (Channels/DMs/Gruppenchats)
- `channel:mail` — E-Mail (z. B. Microsoft365 Outlook)
- `channel:teams-chat` — Microsoft-Teams 1:1-/Gruppen-Chats
- `channel:teams-channel` — Microsoft-Teams-Kanäle
- `channel:whatsapp` — WhatsApp Business (`core.comms.*`)

**Handlungs-Tags** (`action:*`, ein Tool kann mehrere tragen):
- `action:list` / `action:get` / `action:search` — Lese-Operationen
- `action:create` / `action:update` / `action:delete` / `action:send` — Schreib-Operationen
- `action:unread` — Tool liefert (auch) einen Ungelesen-/Unread-Wert. Nur setzen, wenn das Tool diesen Wert
  tatsächlich zurückgibt — sonst bleibt er über `tool_registry.SEARCH` unauffindbar (das war der konkrete
  Auslöser dieser Konvention: `core.comms.wa_overview.GET` berechnet Unread-Kontakte, trug aber kein
  `unread`-Tag).

Der Präfix (`channel:`/`action:`) unterscheidet diese kuratierten Tags bewusst von den bare-word
Auto-Tags, damit `tool_registry.SEARCH(query="channel:mail")` treffsicher genau die E-Mail-Tools findet,
unabhängig davon, ob ein einzelnes Tool zusätzlich `"mail"`, `"email"` oder `"outlook"` als Freitext-Tag
führt.

**Pfadschema für NEUE Kommunikations-Tools**: `<namespace>.<provider?>.<kanal>.<ressource?>.<verb>`
(Beispiel: `user-connectors.microsoft365.teams.chats.list`). Bestehende Tool-Namen/-Pfade werden dafür
NICHT geändert — das Schema gilt nur als Empfehlung für neue Tools, die Tag-Vergabe oben ist additiv und
nicht-brechend für alle bestehenden Tools.

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

