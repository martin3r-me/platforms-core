---
title: Semantic Base Layer — Überblick
order: 1
---

# Semantic Base Layer

Der Semantic Base Layer kodiert die **Identität** von BHG Digital als Verhaltensinstruktion für LLMs — nicht als Inspiration, sondern als maschinenlesbarer Prior, der jedem Plattform-Call vorangestellt wird.

Konzeptionelle Grundlage: [Canvas #50 — Semantic Base Layer](https://office.bhgdigital.de/canvas/50).

---

## Was der Layer ist — und was nicht

**Der Layer ist:**
- Ein versioniertes, strukturiertes JSON-Objekt mit **vier Kanälen**: `perspektive`, `ton[]`, `heuristiken[]`, `negativ_raum[]`
- Ein **harter Prior**, der vor jedem User-Turn in den System-Prompt gemischt wird (bei Frontend-Chat/SSE)
- Ein **Discovery-Feld** für MCP-Clients über `core.context.GET` → `semantic_layer`
- **Scope-aware**: Core (global) + optionale Venture-Extension (team-scoped), Merge via "inherit + extend, never override"

**Der Layer ist nicht:**
- Kein Freitext-Systemprompt — das Schema ist hart auf vier Kanäle fixiert
- Kein Inspirations-Leitbild — jedes Wort muss Modellverhalten messbar verschieben
- Kein Feature-Toggle pro Modul — ob ein Modul den Layer bekommt, entscheidet `enabled_modules` + `status`

---

## Zwei Trägermechanismen, eine Quelle der Wahrheit

```
          ┌──────────────────────────────┐
          │   SemanticLayerResolver      │
          │   (Singleton, 1h Cache)      │
          └──────────┬───────────────────┘
                     │ ResolvedLayer
       ┌─────────────┴─────────────┐
       ▼                           ▼
┌─────────────┐          ┌───────────────────┐
│ MCP         │          │ LLM               │
│ core.context│          │ OpenAiService     │
│ .GET        │          │ system_prompt     │
│ → feld      │          │ ← CoreContextTool │
│   semantic_ │          │   getContext()    │
│   layer     │          │                   │
└─────────────┘          └───────────────────┘
```

Beide Wege rufen denselben `SemanticLayerResolver` auf. Der Layer wird **einmal pro User-Turn** in den System-Prompt gemischt — nicht bei jedem internen Tool-Loop-Zwischencall (Token-Budget-Schutz, siehe [Architektur](architektur)).

---

## Aktueller Stand — V1.0 (Foundation)

| Bereich | Status |
|---|---|
| DB-Schema (3 Tabellen: layers, versions, audit) | ✅ Migriert |
| Resolver + Scaffold + Validator | ✅ implementiert, getestet |
| MCP-Injektion (`GetContextTool`) | ✅ aktiv |
| LLM-Injektion (`CoreContextTool` → `OpenAiService`) | ✅ aktiv (bei `with_context=true`, Default für Frontend-Chat) |
| Cold-Start-Flag `enabled_modules` | ✅ funktional |
| 5 Console-Commands | ✅ verfügbar |
| Debug-UI `/admin/semantic-layer` | ✅ Owner-only Livewire-Panel |
| **Erste Layer-Inhalte** | ⏳ **offen — parallel zur Compression-Arbeit** |
| Lint für Modul-Prompts (V1.1) | ⏳ geplant |
| Scoring-Framework (V1.2) | ⏳ geplant |
| Venture-Extensions live (V1.3) | ⏳ geplant |
| Admin-UI (V2.0) | ⏳ geplant |

Solange keine Layer-Daten in der DB liegen, bleibt das Verhalten der Plattform **identisch zu vorher** — kein Breaking Change.

---

## Navigation

| Seite | Inhalt |
|---|---|
| [Konzept](konzept) | Warum Semantic Layer wirken — Attention-Mechanismus, Kompression, Interference-Problem |
| [Architektur](architektur) | DB-Schema, Resolver-Algorithmus, Cache, Eingriffe in den bestehenden Code |
| [Bedienung](bedienung) | Console-Commands + Debug-UI |
| [Versionierung](versionierung) | SemVer-Semantik, Governance, Migrations-Protokoll |
| [Roadmap](roadmap) | V1.1+ — Lint, Scoring, Venture-Extensions, Production-Rollout |

---

## Kernregel

> Kein Modul bekommt einen Layer, der sein Validierungs-Gate noch nicht bestanden hat.
> Auch nicht unter Zeitdruck. Auch nicht "provisorisch".

Das ist in V1.0 technisch abgesichert: `enabled_modules = []` im Default-State + `status = draft|pilot`. Die Übergangsphase wird **kommuniziert**, nicht durch schlechte Kompromisse überbrückt.
