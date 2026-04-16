---
title: Bedienung
order: 4
---

# Bedienung — Console & Debug-UI

V1.0 arbeitet bewusst **Console-first**. Der Debug-UI (Livewire-Panel) ist eine read-mostly-Schaltzentrale für Pilot-Betrieb, nicht der Editor. Die eigentliche Compression-Arbeit — Inhalte formulieren, kürzen, Negativ-Raum schärfen — bleibt textbasiert.

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

## Debug-UI — `/admin/semantic-layer`

Owner-only Livewire-Panel (`<x-ui-page>` Design). Drei Bereiche:

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

### 3. Layer-Liste mit Status-Switcher + Modul-Toggles

Pro Layer:

- Scope-Label, Status-Badge, aktive SemVer, Token-Count, Versionsanzahl, letztes Update
- **Status-Switcher:** `draft · pilot · production · archived` — Klick setzt sofort
- **Enabled-Modules-Chips:** jeder registrierte Modul-Key wird als klickbarer Chip angezeigt; grün = enabled

Jede Aktion triggert `SemanticLayer::saved` → Cache-Bump → Preview oben aktualisiert sich im selben Request.

### Was die UI bewusst **nicht** kann

- JSON-Inhalt editieren (Perspektive, Ton, Heuristiken, Negativ-Raum) — das ist Compression-Arbeit, gehört in Editor + Review
- Neue Versionen anlegen — geht nur über `layer:create`
- Venture-Layer erstellen — ebenso

Das Prinzip: **Console für Veränderung, UI für Beobachtung und Schalten**.

---

## Typischer Pilot-Workflow

1. **Layer-Content erarbeiten** in Editor / Canvas → JSON-Datei
2. `php artisan layer:create --scope=global --semver=1.0.0 --from-file=…`
3. `php artisan layer:show --scope=global` → Inhalt verifizieren
4. `php artisan layer:activate --scope=global --semver=1.0.0` → Version als aktiv markieren
5. `php artisan layer:enable-module --scope=global --module=okr` → Modul freischalten
6. `/admin/semantic-layer` öffnen → Preview für `okr` checken
7. Über OKR-Frontend eine echte Anfrage stellen → Tonalität checken
8. Wenn okay: `--module=canvas` zusätzlich, sonst Layer via `layer:create` nachschärfen

Der Sprung auf `status=production` passiert **erst nach erfolgtem Validierungsprotokoll** — in V1.0 qualitativ, ab V1.2 quantitativ via Scoring.
