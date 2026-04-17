---
title: MCP-Tools
order: 5
---

# MCP-Tools — `core.semantic_layer.*`

Sieben Tools im Namensraum `core.semantic_layer.*` machen den Semantic Base Layer auch über die MCP-Schnittstelle editierbar. Damit ist MCP der **dritte gleichwertige Eingriffspunkt** neben den Console-Commands (`layer:*`) und dem Admin-UI (`/admin/semantic-layer`). Alle drei nutzen dieselben Services (`LayerSchemaValidator`, `SemanticLayerScaffold`, `SemanticLayerResolver`) und schreiben dieselben Audit-Einträge.

Konkretes Ziel: ein LLM (z.B. Claude über die MCP-Bridge) kann live mit dem User Layer-Content iterieren — anlegen, prüfen, neue Version, Modul-Toggle, Status — ohne Browser-Wechsel und ohne Console-SSH.

---

## Auth

**Alle sechs Tools sind owner-only.** Das umfasst auch die Lese-Tools, weil Layer-Content vor der Production-Aktivierung sensible Identitäts-Kompression ist. Das Tool gibt `ACCESS_DENIED` zurück, wenn der User nicht OWNER im aktuellen Team ist.

Der Owner-Check + die Auflösung der Scope-Parameter sind im Trait `Platform\Core\Tools\SemanticLayer\AssertsOwnerAccess` gekapselt.

**MCP-Instructions-Injection:** Der Layer wird beim MCP-Boot automatisch in `serverInfo.instructions` injiziert, sofern `mcp` als Modul im Layer enabled ist. Claude.ai empfängt den Layer damit direkt im System-Prompt. Voraussetzung: `core.semantic_layer.module.PATCH` mit `module="mcp"`, `enabled=true` — der Key `mcp` ist als virtuelles Modul in der `ModuleRegistry` registriert.

---

## Gemeinsame Scope-Parameter

Die meisten Tools akzeptieren zwei gemeinsame Parameter:

| Parameter | Typ | Default | Bedeutung |
|---|---|---|---|
| `scope` | `"global"` \| `"team"` | `"global"` | Welcher Layer-Scope. |
| `team_id` | `int` \| `null` | aktiver Team-Kontext | Nur bei `scope=team`. Wenn `null`, wird der Team-Kontext der MCP-Session verwendet (siehe `core.team.switch`). |

Falls `scope=team` ohne `team_id` und ohne aktiven Team-Kontext aufgerufen wird, antwortet das Tool mit `MISSING_TEAM_CONTEXT`.

---

## Tool-Übersicht

| Tool | Zweck | Risiko |
|---|---|---|
| `core.semantic_layer.layers.GET` | Liste aller sichtbaren Layer (global + Team) | safe |
| `core.semantic_layer.layer.GET` | Ein Layer mit vollem Content der current_version | safe |
| `core.semantic_layer.versions.POST` | Neue Version anlegen (auto-activate, Layer-auto-create) | write |
| `core.semantic_layer.status.PATCH` | Status setzen (`draft` / `pilot` / `production` / `archived`) | write |
| `core.semantic_layer.module.PATCH` | Modul-Eintrag in `enabled_modules` togglen | write |
| `core.semantic_layer.resolved.GET` | Live-Preview: was sieht das LLM für `{team, module}`? | safe |
| `core.semantic_layer.dryrun.POST` | Echter LLM-Call mit Layer-Inject → Antwort + layer_meta (A/B-Test) | safe |

---

## `core.semantic_layer.layers.GET`

**Params:** keine.

**Output:**
```json
{
  "ok": true,
  "data": {
    "count": 2,
    "layers": [
      {
        "id": 1,
        "scope_type": "global",
        "scope_id": null,
        "status": "pilot",
        "enabled_modules": ["okr"],
        "current_version": { "id": 3, "semver": "1.1.0", "token_count": 180 },
        "version_count": 3,
        "updated_at": "2026-04-16T14:22:00+00:00"
      },
      { "id": 2, "scope_type": "team", "scope_id": 9, ... }
    ]
  }
}
```

---

## `core.semantic_layer.layer.GET`

**Params:** `scope`, `team_id` (optional).

**Output:** Wie oben, plus vollständiger Content der `current_version` (Perspektive, Ton, Heuristiken, Negativ-Raum, Notes, SemVer, Token-Count, Created-At).

Fehler `LAYER_NOT_FOUND`, wenn im Scope noch kein Layer existiert — der Aufrufer kann dann mit `versions.POST` einen anlegen.

---

## `core.semantic_layer.versions.POST`

**Kernoperation.** Legt eine neue Version an. Wenn im Scope noch kein Layer existiert, wird er automatisch angelegt (Status: `pilot`, `enabled_modules: []`).

**Params:**
```json
{
  "scope": "global",
  "team_id": null,
  "semver": "1.0.0",
  "version_type": "minor",
  "perspektive": "Wir sind…",
  "ton": ["klar", "direkt"],
  "heuristiken": ["Im Zweifel: weniger sagen.", "…"],
  "negativ_raum": ["keine Buzzwords", "…"],
  "notes": null
}
```

**Wichtig:**

- `semver` ist **explizit** als `MAJOR.MINOR.PATCH`-String erforderlich — kein Auto-Bump (Aufrufer entscheidet bewusst).
- Die neue Version wird **automatisch** als `current_version_id` aktiviert (Auto-Activate, abweichend zur Console).
- Der Status des Layers wird **nicht** verändert — neuer Layer bleibt auf `pilot`, bestehender behält seinen Status.

**Output:**
```json
{
  "ok": true,
  "data": {
    "layer_id": 1,
    "layer_was_new": true,
    "version_id": 12,
    "semver": "1.0.0",
    "version_type": "minor",
    "token_count": 180,
    "rendered_block": "[SEMANTIC LAYER · v1.0.0]\nPerspektive: …\n…\n[/SEMANTIC LAYER]",
    "status": "pilot",
    "enabled_modules": [],
    "budget_warning": null
  }
}
```

**Fehler:**

- `VALIDATION_ERROR` — Schema-Verstoß (leeres Array, zu langer String, fehlendes Pflichtfeld, ungültiger SemVer-Format).
- `VERSION_EXISTS` — SemVer existiert bereits im Scope.

**Budget-Warning:** Wenn `token_count < 80` oder `> 250`, ist `budget_warning` ein erklärender Hinweis. Soft-Fail — die Version wird trotzdem gespeichert. Der Aufrufer kann den User darauf hinweisen.

---

## `core.semantic_layer.status.PATCH`

**Params:**
```json
{
  "scope": "global",
  "team_id": null,
  "status": "production"
}
```

Erlaubte Werte: `draft`, `pilot`, `production`, `archived`.

**Output:**
```json
{
  "ok": true,
  "data": {
    "layer_id": 1,
    "status": "production",
    "previous_status": "pilot",
    "changed": true,
    "warning_production_broadens_scope": "Der Layer wirkt ab jetzt auf ALLEN Modulen, unabhängig von enabled_modules."
  }
}
```

`warning_production_broadens_scope` erscheint **nur** beim Wechsel auf `production` — informativer Hint, kein Block.

**Archivieren:** Es gibt kein eigenes Delete-Tool. Zum Archivieren `status=archived` setzen — Versionen sind immutable, der Layer bleibt mit Audit-Historie erhalten.

---

## `core.semantic_layer.module.PATCH`

**Params:**
```json
{
  "scope": "global",
  "team_id": null,
  "module": "okr",
  "enabled": true
}
```

Der Modul-Key wird gegen die existierenden Module geprüft (DB + In-Memory-`ModuleRegistry`). Unbekannte Keys → `UNKNOWN_MODULE`.

**Output:**
```json
{
  "ok": true,
  "data": {
    "layer_id": 1,
    "module": "okr",
    "enabled": true,
    "changed": true,
    "enabled_modules": ["okr", "canvas"]
  }
}
```

---

## `core.semantic_layer.resolved.GET`

Test-Spiegel zu `core.context.GET`. Zeigt, was das LLM für eine konkrete `{team, module}`-Kombi tatsächlich sehen würde.

**Params:**
```json
{
  "team_id": null,
  "module": "okr"
}
```

Beide Parameter sind optional. `team_id=null` → nur Global-Layer (entspricht Queue-Job- / API-Kontext). `module=null` → Discovery-Modus (Modul-Gate wird ignoriert).

**Output bei aktivem Layer:**
```json
{
  "ok": true,
  "data": {
    "active": true,
    "perspektive": "…",
    "ton": [...],
    "heuristiken": [...],
    "negativ_raum": [...],
    "scope_chain": ["global"],
    "version_chain": ["1.0.0"],
    "token_count": 180,
    "rendered_block": "[SEMANTIC LAYER · v1.0.0]\n…",
    "team_id": null,
    "module": "okr"
  }
}
```

**Output bei leerem Resolver:**
```json
{
  "ok": true,
  "data": {
    "active": false,
    "reason": "module_not_enabled",
    "team_id": null,
    "module": "okr"
  }
}
```

Mögliche `reason`-Werte:

- `no_layer_in_scope` — gar kein Layer im Scope vorhanden
- `no_active_version` — Layer existiert, hat aber keine `current_version_id`
- `status_not_active` — Layer ist auf `draft` oder `archived`
- `module_not_enabled` — Layer aktiv, aber das angefragte Modul ist nicht in `enabled_modules` und der Layer steht nicht auf `production`
- `unknown` — Fallback (sollte nie vorkommen)

---

## `core.semantic_layer.dryrun.POST`

**Zweck:** echter LLM-Call gegen `OpenAiService::chat()` mit automatischer Layer-Injektion. Damit ist die Wirkung des Layers **serverseitig verifizierbar** — die Antwort ist 1:1 das Modell-Output, nicht vom rufenden Client zusammengestellt.

Nicht für Produktiv-Use gedacht — reines Test-/Iterations-Werkzeug, owner-only. Kein Tool-Loop, kein Streaming, keine Persistenz (kein Audit).

**Params:**
```json
{
  "prompt": "Was sollte ich heute als erstes anpacken?",
  "module": "planner",
  "system": null,
  "max_tokens": 500,
  "temperature": 0.7,
  "model": null
}
```

- `prompt` — Pflicht, 1..2000 Zeichen.
- `module` — optional. Wird in `options.source_module` an OpenAiService durchgereicht, damit der Resolver die richtige `{team, module}`-Kombi sieht (statt des aktiven Session-Moduls `mcp`).
- `system` — optionaler zusätzlicher System-Prompt-Prefix. **Ersetzt** den Layer-System-Prompt nicht — der Layer bleibt injiziert.
- `max_tokens` — Default 500, Hard-Cap 2000.
- `temperature` — Default 0.7.
- `model` — Default: OpenAiService-Default.

**Output bei aktivem Layer:**
```json
{
  "ok": true,
  "data": {
    "content": "…die LLM-Antwort…",
    "model": "gpt-5",
    "layer_active": true,
    "layer_meta": {
      "scope_chain": ["global"],
      "version_chain": ["1.0.0"],
      "token_count": 46,
      "rendered_block": "[SEMANTIC LAYER · v1.0.0]\n…\n[/SEMANTIC LAYER]"
    },
    "module": "planner",
    "team_id": 9,
    "usage": { "input_tokens": 123, "output_tokens": 87 }
  }
}
```

**Output bei inaktivem Layer:**
```json
{
  "ok": true,
  "data": {
    "content": "…neutrale LLM-Antwort…",
    "model": "gpt-5",
    "layer_active": false,
    "layer_meta": null,
    "reason": "status_not_active",
    "module": "planner",
    "team_id": 9,
    "usage": { ... }
  }
}
```

`reason` spiegelt die gleichen Werte wie `core.semantic_layer.resolved.GET`: `no_layer_in_scope`, `no_active_version`, `status_not_active`, `module_not_enabled`, `unknown`.

**Fehler:**

- `ACCESS_DENIED` — non-Owner.
- `VALIDATION_ERROR` — `prompt` fehlt/zu lang, `max_tokens`/`temperature` ungültig, `module`/`system`/`model` falscher Typ.
- `LLM_ERROR` — OpenAI-Call schlägt fehl (enthält die Original-Exception-Message aus `OpenAiService`).

**A/B-Test-Workflow:**

1. Status auf `pilot` setzen (`core.semantic_layer.status.PATCH`), Modul via `core.semantic_layer.module.PATCH` enablen.
2. Dryrun mit `module`-Param aufrufen → Antwort sollte die Layer-Perspektive/Ton zeigen, `layer_active: true`.
3. Status auf `draft` zurücksetzen.
4. Gleichen Dryrun nochmal → Antwort neutral, `layer_active: false`, `reason: status_not_active`.

Der Stil-Unterschied zwischen 2. und 4. ist die serverseitig messbare Layer-Wirkung.

---

## Bewusste Nicht-Tools

- **Kein `archive` / `delete`-Tool** — `status=archived` über `status.PATCH` reicht. Versionen sind immutable, Layer nicht löschbar.
- **Kein `rollback`-Tool** — Rollback = `versions.POST` mit altem Content unter neuer SemVer. Der Aufrufer baut sich das aus `layer.GET` + `versions.POST` selbst zusammen.
- **Kein Auto-Bump des SemVer** — bleibt UI-Komfort. MCP soll explizit sein, weil API-Ebene.
- **Keine Batch-Edits** — jedes Tool eine Operation. Batching macht der Aufrufer.

---

## Risiken & Schutzmechanismen

| Risiko | Schutz |
|---|---|
| LLM erzeugt schlampigen Layer-Content via POST | Owner-Only + Schema-Validator + Budget-Warning + alle Versionen immutable + jederzeit Rollback via neuer Version |
| User springt versehentlich auf `production` | `warning_production_broadens_scope` im Output (V1.2: Scoring-Gate) |
| MCP-Discovery zeigt Tools auch non-Owner | Tools bleiben sichtbar, Ausführung schlägt mit `ACCESS_DENIED` fehl |
| Modul-Key-Typo blockiert nicht den Save | Vorab-Check gegen DB-Module + ModuleRegistry → `UNKNOWN_MODULE` |
| Team-Scope-Operation ohne Team-Kontext | Klare Fehlermeldung `MISSING_TEAM_CONTEXT` |

---

## Verwandte Eingriffspunkte

- **Console** — `php artisan layer:create | layer:list | layer:activate | layer:enable-module | layer:show` (siehe [Bedienung](bedienung))
- **Admin-UI** — `/admin/semantic-layer` (Owner-only Livewire-Panel mit Live-Preview)
