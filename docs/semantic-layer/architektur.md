---
title: Architektur
order: 3
---

# Technische Architektur

Diese Seite beschreibt, *wie* der Layer im Code umgesetzt ist — DB-Schema, Resolver-Algorithmus, Cache-Strategie, Eingriffs-Stellen.

---

## Datei-Übersicht

```
src/SemanticLayer/
├── Models/
│   ├── SemanticLayer.php          # scope_type, scope_id, status, enabled_modules
│   ├── SemanticLayerVersion.php   # semver, vier Kanäle, token_count
│   └── SemanticLayerAudit.php     # action, diff, user_id, context
├── DTOs/
│   └── ResolvedLayer.php          # immutable readonly, final
├── Services/
│   ├── SemanticLayerResolver.php  # Kern: scope-aware merge + cache
│   └── SemanticLayerScaffold.php  # Prompt-Block-Renderer
├── Schema/
│   └── LayerSchemaValidator.php   # harte 4-Kanal-Validierung
├── Exceptions/
│   ├── InvalidLayerSchemaException.php
│   └── LayerNotResolvableException.php
├── Console/Commands/
│   ├── LayerCreateCommand.php
│   ├── LayerListCommand.php
│   ├── LayerActivateCommand.php
│   ├── LayerEnableModuleCommand.php
│   └── LayerShowCommand.php
└── SemanticLayerServiceProvider.php
```

Nur zwei bestehende Dateien wurden modifiziert:

- `src/Tools/CoreContextTool.php` — LLM-Eingriff (System-Prompt)
- `src/Tools/GetContextTool.php` — MCP-Eingriff (Context-Response)

---

## Datenbankschema

Drei Tabellen, Migrations-Zeitpunkt `2026-04-16`.

### `semantic_layers`

```
id                   bigint PK
scope_type           enum('global','team')
scope_id             bigint NULL  FK → teams
current_version_id   bigint NULL  FK → semantic_layer_versions
status               enum('draft','pilot','production','archived')
enabled_modules      json  default '[]'
created_at, updated_at
UNIQUE(scope_type, scope_id)      # max ein Layer pro Scope
```

### `semantic_layer_versions`

```
id                bigint PK
semantic_layer_id bigint FK → semantic_layers  ON DELETE CASCADE
semver            varchar(20)      # '1.0.0'
version_type      enum('major','minor','patch')
perspektive       text             # 1..500 Zeichen
ton               json             # array of string, max 12
heuristiken       json             # array of string, max 12
negativ_raum      json             # array of string, max 12
token_count       integer          # automatisch mb_strlen/4 Approximation
notes             text NULL        # Negativ-Dokumentation
created_by        bigint FK → users
created_at        timestamp
UNIQUE(semantic_layer_id, semver)
```

### `semantic_layer_audit`

```
id                bigint PK
semantic_layer_id bigint FK
version_id        bigint NULL FK
action            varchar(40)      # 'created','activated','enabled_module','archived',...
diff              json NULL        # strukturierter Diff
user_id           bigint NULL FK
context           json NULL        # {module,reason,...}
created_at
INDEX (semantic_layer_id, created_at)
```

---

## Schema-Validierung

`LayerSchemaValidator` prüft beim `saving`-Event der Version:

| Feld | Regel |
|---|---|
| `perspektive` | string, 1–500 Zeichen |
| `ton[]` | array von string, max 12 Items, je 1–120 Zeichen |
| `heuristiken[]` | array von string, max 12 Items, je 1–200 Zeichen |
| `negativ_raum[]` | array von string, max 12 Items, je 1–120 Zeichen |
| Extra-Keys | **hart abgelehnt** |

Token-Budget: `token_count ≈ mb_strlen($rendered) / 4`. Warnung (soft) unter 80 oder über 250.

---

## Der Resolver-Algorithmus

Eine einzige Public-Methode:

```php
public function resolveFor(?Team $team, ?string $module): ResolvedLayer;
```

**Schritte:**

1. **Global-Layer laden** (scope_type=global, aktive Version)
2. **Team-Scope-Layer laden** — wenn `$team` gesetzt
3. **Module-Gate prüfen:**
   - Status `production` → Gate durchbrochen, Layer wirkt immer
   - Status `pilot` + `$module` in `enabled_modules` → aktiv
   - Status `pilot` + `$module === null` (Discovery) → aktiv (für MCP-Handshake)
   - sonst → `ResolvedLayer::empty()`
4. **Merge** (wenn beide Ebenen da):
   - `perspektive`: Extension override, sonst Core
   - `ton[]`, `heuristiken[]`, `negativ_raum[]`: append + case-insensitive dedup
5. **Render** via `SemanticLayerScaffold::render()` — erzeugt den finalen Prompt-Block
6. **Cache** (1h TTL)

Defensiv: jeder Fehlschritt im Resolver wird geloggt und gibt `empty()` zurück. Die Plattform darf **nie** an der Layer-Auflösung scheitern.

---

## Das Prompt-Block-Format

`SemanticLayerScaffold` rendert fixiertes Format mit Pseudo-Tags, damit das Modell den Block als kohärente Einheit verarbeitet:

```
[SEMANTIC LAYER · v1.0.0 + v0.2.0]
Perspektive: Wir sind ehrliche Handwerker.

Ton:
- klar
- direkt
- präzise

Heuristiken (im Zweifel):
- Im Zweifel: weniger sagen.
- Im Venture gilt: weniger ist mehr.

Was wir nie sagen / sind:
- keine Buzzwords
- keine Fachwörter
[/SEMANTIC LAYER]
```

- Reihenfolge fix: Perspektive → Ton → Heuristiken → Negativ-Raum
- Begründung: frühe Tokens haben höchste Attention → Identitäts-Anker zuerst
- Version-Chain als Header → bei Merge `v1.0.0 + v0.2.0`

---

## Cache-Strategie

Schlüssel-Schema:

```
semantic_layer:resolved:b{bump}:t{team_id}:m{module}:gv{global_version}:tv{team_version}
```

- TTL: **3600 Sekunden**
- `bump` ist ein separat gecachter Counter-Integer
- **Invalidierung:** statt Tag-Flush (nicht in allen Cache-Drivern verfügbar) wird der Counter inkrementiert → alle alten Keys werden wirkungslos

Auslöser im `SemanticLayerServiceProvider`:

```php
SemanticLayer::saved(fn() => SemanticLayerResolver::forgetCache());
SemanticLayer::deleted(fn() => SemanticLayerResolver::forgetCache());
SemanticLayerVersion::saved(fn() => SemanticLayerResolver::forgetCache());
SemanticLayerVersion::deleted(fn() => SemanticLayerResolver::forgetCache());
```

Praktisch: jede Änderung im Debug-UI (Status ändern, Modul togglen) bumpt den Counter → sofort sichtbar im nächsten Resolve.

---

## Eingriff 1 — LLM über `CoreContextTool`

Ort: `src/Tools/CoreContextTool.php` (Zeilen 43–61).

Ablauf:

1. `SemanticLayerResolver` im Try/Catch auflösen
2. `$layerBlock` = `$resolved->rendered_block` oder `null`
3. Base-Instruction (Tool-Nutzung, Deutsch, Scope) bleibt separat erhalten
4. Final: `trim($layerBlock . "\n\n" . $baseInstruction)` oder nur `$baseInstruction`

Das Feld `semantic_layer` wird zusätzlich ins Response-Array aufgenommen — read-only für LLM-Konsumenten, die das Debuggen wollen.

**Modul-Detection** leitet sich aus dem Laravel-Route-Namen ab:

```php
// 'planner.projects.index' → $module = 'planner'
if (is_string($routeName) && str_contains($routeName, '.')) {
    $module = strstr($routeName, '.', true);
}
```

Bei Queue-Jobs, Console oder MCP-Discovery existiert kein Route-Name → `$module = null` → greift nur via `status=production`.

---

## Eingriff 2 — MCP über `GetContextTool`

Ort: `src/Tools/GetContextTool.php` nach dem `team`-Block.

Ablauf:

1. `$team = $context->team` (Request-Team, nicht User-currentTeam)
2. `$module = $context->metadata['module'] ?? null`
3. Resolver aufrufen, Result nur wenn nicht empty ins `$result` mergen

Damit sieht ein MCP-Client den Layer beim **allerersten Handshake** — der Pflicht-Eintrittspunkt laut MCP-Konvention. Felder: `perspektive`, `ton`, `heuristiken`, `negativ_raum`, `scope_chain`, `version_chain`, `token_count`, `rendered_block`.

---

## Wo der Layer **nicht** injiziert wird

Bewusst ausgelassen, aus Token-Ökonomie:

| Ort | Warum |
|---|---|
| `AiToolLoopRunner` interne Sub-Calls | `with_context=false` — Loop-Zwischen-Calls würden Layer x-fach inflationieren |
| `SimpleToolController` Tool-Routing | `with_context=false` — interne Klassifikation braucht keine Identität |
| `OpenAiService` PreFlight-Intent | `with_context=false` — Intent-Klassifikation ist content-agnostisch |
| Queue-Jobs ohne auth()->user() | Resolver erhält `$team=null` → nur `status=production` greift |

Der Layer wirkt **einmal pro User-Turn** an dem Punkt, an dem das Modell tatsächlich mit dem User kommuniziert. Das ist die Absicht, nicht ein Bug.

---

## Service-Provider

`SemanticLayerServiceProvider` registriert:

- **Singletons:** `SemanticLayerResolver`, `SemanticLayerScaffold`, `LayerSchemaValidator`
- **Console-Commands:** alle 5 `LayerXxxCommand`
- **Model-Event-Listener** für Cache-Invalidation

Auto-Discovery via `composer.json` → `extra.laravel.providers`:

```json
"Platform\\Core\\SemanticLayer\\SemanticLayerServiceProvider"
```
