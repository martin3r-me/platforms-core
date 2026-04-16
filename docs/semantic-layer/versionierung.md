---
title: Versionierung & Governance
order: 5
---

# Versionierung & Governance

Nicht jede Änderung am Layer ist gleich schwer. Deshalb nutzt der Semantic Base Layer **Semantic Versioning** (MAJOR.MINOR.PATCH) — aus der Software-Entwicklung übernommen, weil es das präziseste Vokabular für "wie groß ist diese Änderung?" ist.

Enum-Feld im Code: `SemanticLayerVersion::TYPE_MAJOR | TYPE_MINOR | TYPE_PATCH`.

---

## Die drei Änderungs-Klassen

### PATCH — Schärfung ohne Richtungsänderung

Beispiele: Typo, Wort durch treffenderes Wort ersetzen, Formulierung präzisieren.

- **Aussage bleibt identisch** — nur die Form verbessert sich
- Kein Review-Prozess
- Martin entscheidet allein
- Kein Modul-Test nötig — Modellverhalten ändert sich nicht messbar

```bash
# 1.0.0 → 1.0.1
php artisan layer:create --scope=global --semver=1.0.1 --version-type=patch --from-file=…
php artisan layer:activate --scope=global --semver=1.0.1
```

### MINOR — Erweiterung im bestehenden Rahmen

Beispiele: neue Heuristik, Ton-Instruktion spezifiziert, Negativ-Raum um ein Element erweitert.

- **Kernidentität bleibt** — der Layer wird präziser oder vollständiger
- Validierungstest für Dimension 1 (Ton-Konsistenz) und 2 (Heuristiken-Aktivierung) erforderlich
- Martin entscheidet allein, danach Validierungsprotokoll vor Aktivierung

```bash
# 1.0.1 → 1.1.0
php artisan layer:create --scope=global --semver=1.1.0 --version-type=minor --from-file=…
# ... Validierung ...
php artisan layer:activate --scope=global --semver=1.1.0
```

### MAJOR — Fundamentale Identitätsänderung

Beispiele: neuer Kernwert, Perspektiv-Wechsel, strategischer Pivot.

- Das ist **keine Optimierung** — das ist eine neue Version von BHG Digital
- Vollständiges Validierungsprotokoll über alle vier quantitativen Dimensionen (ab V1.2)
- Review im GF-Kreis vor Aktivierung
- **Migrations-Strategie** für alle Venture-Layer, da sie erben

---

## Migrations-Protokoll bei MAJOR-Release

Kein Big-Bang, sondern schrittweise:

1. **Neue Version parallel aktivieren** via Feature-Flag (Status `pilot`, begrenzte `enabled_modules`)
2. **48h Cross-Modul-Beobachtung** — alter vs. neuer Layer im Vergleich
3. **Venture-Layer prüfen** — alle erbenden Extensions auf Konflikte checken, ggf. anpassen
4. **Freigabe** → Status `production` setzen, alte Version archivieren

In V1.0 passiert Schritt 2 manuell. Ab V1.2 automatisiert via Scoring-Job.

---

## Governance-Matrix

Aus Canvas #50, Block "Governance":

| Change-Klasse | Entscheider | Review |
|---|---|---|
| PATCH | Martin allein | — |
| MINOR | Martin allein | Validierungs-Dimensionen 1+2 |
| MAJOR | Martin + GF-Kreis | Alle 4 Dimensionen + 48h Observation |

**Review-Rhythmus** (laufend): quartalsweise die Drift-Frage stellen — *Würde ich das so unterschreiben?*

Trigger für **sofortige** Überprüfung (nicht "nächstes Quartal"):

- Neues Venture wird integriert
- Strategischer Pivot
- Neue Zielgruppe mit anderer Sprache
- Neuer GF oder Partner, der Kultur aktiv mitprägt
- Feedback aus Nutzung: "Das klingt nicht wie wir"
- Onboarding eines Kunden aus neuer Branche

---

## Audit-Trail

Jede Änderung erzeugt einen Eintrag in `semantic_layer_audit`:

| Aktion | Auslöser |
|---|---|
| `created` | `layer:create` für komplett neuen Scope |
| `version_created` | `layer:create` für zusätzliche Version im bestehenden Scope |
| `activated` | `layer:activate` |
| `status_changed` | Status-Switch im Debug-UI oder via Command |
| `enabled_module` / `disabled_module` | `layer:enable-module` oder UI-Toggle |
| `archived` | manueller Status-Wechsel auf `archived` |

Jeder Eintrag enthält:

- `semantic_layer_id`, `version_id` (optional)
- `action` (String)
- `diff` (JSON, strukturiert: `{field, op, from, to}`)
- `user_id` (wer hat's gemacht)
- `context` (`{module, reason, source}`)
- `created_at`

Die Audit-Chain ist **unveränderbar** — kein Update, kein Delete im Model. Die Tabelle ist der forensische Beleg für jede Identitäts-Mutation.

---

## Security & Access Control

Aus Canvas #50, Block "Security":

**Lese-Zugriff:**
- Alle authentifizierten Plattform-User: `resolved`-Layer-Objekt
- MCP-Consumer (externe Agenten): read-only über `core.context.GET`
- Ventures: **nur eigener resolved-Layer** — kein Zugriff auf andere Venture-Extensions

**Schreib-Zugriff:**
- Core-Layer (Ebene 1): nur Martin Erren + technische Admins
- Venture-Extension (Ebene 2): nur autorisierte GF des Ventures
- Modul-Prompts (Ebene 3): Entwickler-Team, nach Lint-Check (ab V1.1)

**Schutz vor Injection:**
- User-Input kann **nie** in den System-Layer-Block schreiben — die Eingriffspunkte (`CoreContextTool`, `GetContextTool`) lesen ausschließlich aus dem Resolver
- Layer-ähnliche Blöcke im User-Input werden nicht als Layer-Instruktion interpretiert

**Notfall-Protokoll:**
- Status `archived` setzen → Layer wirkt nicht mehr plattformweit
- Alternativ: `enabled_modules = []` + `status = pilot` → kein Modul bekommt ihn
- Beide Operationen greifen innerhalb von Sekunden (Cache-Bump ist synchron)
