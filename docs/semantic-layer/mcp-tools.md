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

**MCP-Instructions-Injection:** Alle Layer, deren `enabled_modules` den Key `mcp` enthält (oder die ungated sind, z.B. Leitbild), werden beim MCP-Boot automatisch in `serverInfo.instructions` injiziert. Claude.ai empfängt den gemergten Layer im System-Prompt. Der Key `mcp` ist in der `ContextKeyRegistry` registriert (nicht mehr `ModuleRegistry`).

---

## Gemeinsame Parameter

Die meisten Tools akzeptieren diese gemeinsamen Parameter zur Layer-Identifikation:

| Parameter | Typ | Default | Bedeutung |
|---|---|---|---|
| `layer_id` | `int` \| `null` | — | Direkte Layer-ID (hat Vorrang vor scope+label). |
| `scope` | `"global"` \| `"team"` | `"global"` | Welcher Layer-Scope. Nur relevant ohne `layer_id`. |
| `team_id` | `int` \| `null` | aktiver Team-Kontext | Nur bei `scope=team`. Wenn `null`, wird der Team-Kontext der MCP-Session verwendet (siehe `core.team.switch`). |
| `label` | `string` | `"leitbild"` | Layer-Label. Pro `scope+label` existiert max. ein Layer. |

**Multi-Layer:** Pro Scope können mehrere Layer mit unterschiedlichen Labels existieren (z.B. `leitbild` + `mcp`). Die `label`-Semantik:
- `"leitbild"` — Ungated, greift überall (sort_order=0)
- beliebig (z.B. `"mcp"`) — Muss über `enabled_modules` gegateted werden (sort_order=10)

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
    "count": 3,
    "layers": [
      {
        "id": 1,
        "scope_type": "global",
        "scope_id": null,
        "label": "leitbild",
        "sort_order": 0,
        "status": "pilot",
        "enabled_modules": [],
        "is_ungated": true,
        "current_version": { "id": 3, "semver": "1.1.0", "token_count": 180 },
        "version_count": 3,
        "updated_at": "2026-04-16T14:22:00+00:00"
      },
      {
        "id": 4,
        "scope_type": "global",
        "scope_id": null,
        "label": "mcp",
        "sort_order": 10,
        "status": "pilot",
        "enabled_modules": ["mcp"],
        "is_ungated": false,
        "current_version": { "id": 5, "semver": "0.2.0", "token_count": 46 },
        "version_count": 2,
        "updated_at": "2026-04-17T10:00:00+00:00"
      },
      { "id": 2, "scope_type": "team", "scope_id": 9, "label": "leitbild", ... }
    ]
  }
}
```

---

## `core.semantic_layer.layer.GET`

**Params:** `layer_id` (direkt) ODER `scope`, `team_id`, `label` (optional, Default label: `"leitbild"`).

**Output:** Wie oben, plus vollständiger Content der `current_version` (Perspektive, Ton, Heuristiken, Negativ-Raum, Notes, SemVer, Token-Count, Created-At) sowie `label`, `sort_order`, `is_ungated`.

Fehler `LAYER_NOT_FOUND`, wenn kein Layer für die Kombination existiert — der Aufrufer kann dann mit `versions.POST` einen anlegen.

---

## `core.semantic_layer.versions.POST`

**Kernoperation.** Legt eine neue Version an. Wenn im Scope+Label noch kein Layer existiert, wird er automatisch angelegt (Status: `pilot`, `enabled_modules: []`).

**Params:**
```json
{
  "scope": "global",
  "team_id": null,
  "label": "leitbild",
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

- `label` wählt den Layer (Default: `"leitbild"`). Bei `label="leitbild"` wird `sort_order=0` gesetzt, bei anderen Labels `sort_order=10`.
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

**Params:** `layer_id` (direkt) ODER `scope + label`. Plus:
```json
{
  "scope": "global",
  "label": "leitbild",
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

**Params:** `layer_id` (direkt) ODER `scope + label`. Plus:
```json
{
  "scope": "global",
  "label": "mcp",
  "module": "mcp",
  "enabled": true
}
```

Der Kontext-Key wird gegen die `ContextKeyRegistry` geprüft (DB-Module + Builtins wie `mcp`, `api`, `webhook`). Unbekannte Keys → `UNKNOWN_MODULE`.

**Output:**
```json
{
  "ok": true,
  "data": {
    "layer_id": 4,
    "label": "mcp",
    "module": "mcp",
    "enabled": true,
    "changed": true,
    "enabled_modules": ["mcp"]
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

Beide Parameter sind optional. `team_id=null` → nur Global-Layer. `module=null` → nur ungated Leitbild-Layer.

**Output bei aktivem Layer (Multi-Layer Merge):**
```json
{
  "ok": true,
  "data": {
    "active": true,
    "perspektive": "…",
    "ton": [...],
    "heuristiken": [...],
    "negativ_raum": [...],
    "scope_chain": ["global:leitbild", "global:mcp", "team:9:leitbild"],
    "version_chain": ["1.0.0", "0.2.0", "0.1.0"],
    "token_count": 226,
    "rendered_block": "[SEMANTIC LAYER · leitbild:v1.0.0 + mcp:v0.2.0 + leitbild:v0.1.0]\n…",
    "team_id": 9,
    "module": "mcp"
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
| Kontext-Key-Typo blockiert nicht den Save | Vorab-Check gegen `ContextKeyRegistry` (Module + Builtins) → `UNKNOWN_MODULE` |
| Team-Scope-Operation ohne Team-Kontext | Klare Fehlermeldung `MISSING_TEAM_CONTEXT` |

---

## Verwandte Eingriffspunkte

- **Console** — `php artisan layer:create | layer:list | layer:activate | layer:enable-module | layer:show` (siehe [Bedienung](bedienung))
- **Admin-UI** — `/admin/semantic-layer` (Owner-only Livewire-Panel mit Live-Preview)
