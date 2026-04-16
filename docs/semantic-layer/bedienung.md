---
title: Bedienung
order: 4
---

# Bedienung — Console, Admin-UI & MCP

V1.0 bietet **drei gleichwertige Eingriffspunkte**: die Console-Commands (für CI/Seeder/Skripte und dateibasiertes Arbeiten), das Livewire-Admin-Panel unter `/admin/semantic-layer` (für schnelle inhaltliche Iteration mit Live-Preview) und die MCP-Tools `core.semantic_layer.*` (damit ein LLM live mit dem Owner Layer-Content iterieren kann — ohne Browser-Wechsel, ohne Console-SSH). Alle drei nutzen denselben Validator, Scaffold und Resolver — es gibt keine UI-Sonderlogik.

Die eigentliche Compression-Arbeit — Inhalte formulieren, kürzen, Negativ-Raum schärfen — bleibt textbasierte Denkarbeit. Die UI macht das Iterieren schneller, ersetzt die Arbeit aber nicht.

---

## Console-Commands

Alle Commands liegen in `src/SemanticLayer/Console/Commands/`.

### `layer:create` — neuen Layer oder Version anlegen

```bash
# Neuer globaler Layer mit erster Version aus Datei
php artisan layer:create \
    --scope=global \
    --semver=1.0.0 \
    --version-type=minor \
    --from-file=/tmp/layer-v1.json

# Venture-Extension für ein Team
php artisan layer:create \
    --scope=team --team-id=9 \
    --semver=0.1.0 --version-type=minor \
    --from-file=/tmp/rheingedeck-ext.json

# Interaktiv via $EDITOR
php artisan layer:create --scope=global --semver=1.0.0 --editor

# Aus stdin (für CI/Seeder)
cat layer.json | php artisan layer:create --scope=global --semver=1.0.0 --from-stdin
```

Format der JSON-Datei — exakt die vier Kanäle:

```json
{
  "perspektive": "Wir sind ehrliche Handwerker, die Werkzeuge zuerst für sich selbst bauen.",
  "ton": ["klar", "direkt", "kurze Sätze", "aktiv"],
  "heuristiken": [
    "Im Zweifel: weniger sagen.",
    "Outcome immer explizit machen.",
    "Keine Lösung ohne Problemdefinition."
  ],
  "negativ_raum": [
    "keine Buzzwords",
    "kein Weichspüler",
    "nie vage Versprechen"
  ]
}
```

Bestehender Layer + neue Version? Derselbe Command mit neuem `--semver`. Der Scope-Check verhindert, dass versehentlich ein zweiter Layer im selben Scope entsteht.

### `layer:list` — Übersicht

```bash
php artisan layer:list
```

Tabelle: ID, Scope, Status, aktive SemVer, Anzahl Versionen, enabled_modules.

### `layer:activate` — Version aktivieren / Status setzen

```bash
# Version 1.0.0 als aktuelle Version des globalen Layers setzen (bleibt pilot)
php artisan layer:activate --scope=global --semver=1.0.0

# Gleichzeitig in Production heben
php artisan layer:activate --scope=global --semver=1.0.0 --status=production
```

Ein Layer **ohne aktive Version** wirkt nie — das ist der Schutz vor halbfertigen Layern in Produktion.

### `layer:enable-module` — Modul-Flag togglen

```bash
# OKR für den globalen Layer freischalten (Pilot-Start)
php artisan layer:enable-module --scope=global --module=okr

# Canvas zusätzlich
php artisan layer:enable-module --scope=global --module=canvas

# Wieder deaktivieren
php artisan layer:enable-module --scope=global --module=okr --disable
```

Cold-Start-Regel: **Kein Modul wirkt ohne expliziten Enable-Eintrag**, solange der Layer auf `status=pilot` steht. Ausnahme: `status=production` — dann wirken alle Module.

### `layer:show` — Inhalt inspizieren

```bash
# Roh-Layer eines Scopes anzeigen
php artisan layer:show --scope=global

# Resolved (gemerged) für ein Modul ansehen — entspricht dem, was das LLM sieht
php artisan layer:show --resolved --module=okr

# Mit Team-Scope
php artisan layer:show --resolved --team-id=9 --module=okr

# JSON-Output für Pipes
php artisan layer:show --resolved --module=okr --json
```

---

## Admin-UI — `/admin/semantic-layer`

Owner-only Livewire-Panel (`<x-ui-page>` Design). Vier Bereiche:

### 1. Modul-Vorschau-Selector

Button-Reihe mit allen registrierten Modulen + `— kein Modul —`. Steuert, mit welchem Modul-Kontext die Preview unten aufgelöst wird.

### 2. Resolved-Previews (Side-by-Side)

- **Aktuelles Team** — zeigt, was der Nutzer im eigenen Team-Scope sehen würde (inkl. Team-Extension, falls vorhanden)
- **Ohne Team** — nur der globale Layer, wie er bei Queue-Jobs / Console / externer API ankommt

Jede Preview zeigt:

- Token-Count
- Scope-Chain (`global` oder `global → team:9`)
- Den fertigen `rendered_block` — exakt wie im System-Prompt

Wenn ein Scope nichts liefert (Layer fehlt, Modul nicht enabled, Status draft): `Kein Layer aktiv für diese Kombination.`

### 3. Inline-Editor (Level A)

Zwei Einstiegspunkte über der Layer-Liste:

- **`+ Global-Layer anlegen`** — erscheint nur, solange kein globaler Layer existiert. Öffnet ein Formular für die erste Version `v1.0.0`.
- **`+ Extension für «{Team}»`** — erscheint, wenn das aktuelle Team noch keinen Extension-Layer hat. Legt einen Team-Scope-Layer mit `v0.1.0` an.
- **`+ Neue Version`** — pro bestehendem Layer; Formular wird mit dem aktuellen Content vorbefüllt, SemVer wird abhängig vom gewählten `version-type` automatisch hochgezählt.

Das Formular enthält:

| Feld | Verhalten |
|---|---|
| `SemVer` | Freies Feld, Format `MAJOR.MINOR.PATCH`, Vorschlag nach Version-Type |
| `Version-Type` | `patch` / `minor` / `major` — steuert den automatischen SemVer-Bump |
| `Perspektive` | Textarea + Live-Counter `x/500` |
| `Ton` | Textarea, eine Zeile pro Item, max 12 |
| `Heuristiken` | Textarea, eine Zeile pro Item, max 12 |
| `Negativ-Raum` | Textarea, eine Zeile pro Item, max 12 |
| `Notizen` | Optional — Platz für Negativ-Dokumentation |

Rechts neben dem Formular läuft eine **Live-Preview** des `rendered_block` mit:

- **Live-Token-Count** (debounced 400 ms)
- **Budget-Ampel:** grün im Soft-Bereich 80–250 (Ziel 150–200), amber außerhalb
- **Schema-Errors inline** als rote Liste, wenn der Validator fehlschlägt (leeres Array, zu langer String, unerlaubte Felder)

Beim Speichern:

1. Validator läuft (`LayerSchemaValidator`)
2. Neue Version wird angelegt (alte bleiben immutable in der DB)
3. Neue Version wird **automatisch als `current_version` aktiviert** — Status des Layers bleibt unverändert
4. Audit-Eintrag mit strukturiertem Feld-Diff wird geschrieben
5. Resolver-Cache wird invalidiert → Preview oben aktualisiert sich sofort

### 4. Layer-Liste mit Status-Switcher + Modul-Toggles

Pro Layer:

- Scope-Label, Status-Badge, aktive SemVer, Token-Count, Versionsanzahl, letztes Update
- **`+ Neue Version`** (siehe Level-A-Editor oben)
- **Status-Switcher:** `draft · pilot · production · archived` — Klick setzt sofort
- **Enabled-Modules-Chips:** jeder registrierte Modul-Key wird als klickbarer Chip angezeigt; grün = enabled

Jede Aktion triggert `SemanticLayer::saved` → Cache-Bump → Preview oben aktualisiert sich im selben Request. Status- und Modul-Änderungen landen im Audit (`status_changed`, `enabled_module`, `disabled_module`).

### Was die UI bewusst **nicht** kann

- **Bestehende Versionen editieren** — Versionen sind immutable. Jede Änderung ist eine neue Version.
- **Versionshistorie / Diff-Ansicht** — kommt in Level B (siehe [Roadmap](roadmap))
- **Audit-Log-UI** — kommt in Level C
- **Dateibasiertes Arbeiten** — für CI, Seeder und stdin-Pipes bleibt `layer:create --from-file=` / `--from-stdin` der richtige Weg
- **Production-Sprung mit Validierungsprotokoll** — Status auf `production` setzen ist ein Klick; das qualitative Go/No-Go bleibt menschliche Entscheidung bis V1.2 Scoring steht

Das Prinzip: **UI für schnelle Iteration + Beobachtung, Console für Pipelines**.

---

## Typische Workflows

### Variante A — UI-first (empfohlen für Iteration)

1. `/admin/semantic-layer` öffnen
2. **`+ Global-Layer anlegen`** → Formular ausfüllen, Live-Preview beobachten, Token-Budget prüfen → **Speichern**
3. Im Modul-Vorschau-Selector `okr` wählen → Preview für OKR-Kontext checken
4. In der Layer-Liste **Enabled Modules** → `okr` togglen (Chip wird grün)
5. Über OKR-Frontend eine echte Anfrage stellen → Tonalität checken
6. Nachschärfen: **`+ Neue Version`** am Layer → Form ist vorbefüllt → bearbeiten → **Speichern** (neue Version wird automatisch aktiv)

### Variante B — Console-first (für CI / dateibasiertes Arbeiten)

1. **Layer-Content erarbeiten** in Editor → JSON-Datei
2. `php artisan layer:create --scope=global --semver=1.0.0 --from-file=…`
3. `php artisan layer:show --scope=global` → Inhalt verifizieren
4. `php artisan layer:activate --scope=global --semver=1.0.0` → Version als aktiv markieren
5. `php artisan layer:enable-module --scope=global --module=okr` → Modul freischalten
6. `/admin/semantic-layer` → Preview + echten Test wie in Variante A

### Go/No-Go für Production

Der Sprung auf `status=production` passiert **erst nach erfolgtem Validierungsprotokoll** — in V1.0 qualitativ (Blind-Test, Tonalität-Check), ab V1.2 quantitativ via Scoring. Der Status-Switcher im UI sollte nicht ohne diesen Schritt auf `production` gezogen werden.

---

## MCP — `core.semantic_layer.*`

Sieben Tools im Namensraum `core.semantic_layer.*` machen alle UI-/Console-Aktionen auch über die MCP-Schnittstelle verfügbar — plus einen **Dryrun**-Pfad, der serverseitig einen echten LLM-Call mit Layer-Inject triggert und die Antwort 1:1 zurückgibt (A/B-Verifikation). Anwendungsfall: ein LLM (z.B. Claude über die MCP-Bridge) iteriert live mit dem Team-Owner — Layer anlegen, Preview prüfen, neue Version, Modul-Toggle, Status-Wechsel, LLM-Test — alles im selben Chat.

| Tool | Entspricht UI/Console |
|---|---|
| `core.semantic_layer.layers.GET` | Layer-Liste / `layer:list` |
| `core.semantic_layer.layer.GET` | Layer-Detail / `layer:show` |
| `core.semantic_layer.versions.POST` | „+ Neue Version" / `layer:create` (auto-aktiviert) |
| `core.semantic_layer.status.PATCH` | Status-Switcher / `layer:activate --status=…` |
| `core.semantic_layer.module.PATCH` | Modul-Chip / `layer:enable-module` |
| `core.semantic_layer.resolved.GET` | Resolved-Preview / `layer:show --resolved` |
| `core.semantic_layer.dryrun.POST` | Serverseitiger LLM-Call mit Layer-Inject (A/B-Test) |

**Auth:** alle Tools owner-only (identisch zur UI). Ausführung als Non-Owner → `ACCESS_DENIED`.

**Detaillierte Tool-Referenz** mit Parametern, Beispielen, Fehler-Codes: siehe [MCP-Tools](mcp-tools).
