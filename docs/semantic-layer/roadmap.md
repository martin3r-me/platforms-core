---
title: Roadmap
order: 6
---

# Roadmap

V1.0 ist **Infrastruktur**. Der Code läuft, die Injektion wirkt, das Debug-UI steht — aber der eigentliche Wert entsteht erst durch Inhalt und die nachfolgenden Phasen. Dieses Dokument skizziert, was kommt.

---

## V1.0 — Foundation (erledigt)

| Deliverable | Status |
|---|---|
| DB-Schema (3 Tabellen) | ✅ |
| Resolver + Scaffold + Validator | ✅ |
| MCP-Integration (`GetContextTool`) | ✅ |
| LLM-Integration (`CoreContextTool` → `OpenAiService`) | ✅ |
| Cold-Start-Flag `enabled_modules` | ✅ |
| 5 Console-Commands | ✅ |
| Debug-UI `/admin/semantic-layer` | ✅ |
| Unit- & Feature-Tests | ✅ |

**Kein Breaking Change**: Solange keine Layer-Daten in der DB sind, ist das Verhalten identisch zu vorher.

---

## Unmittelbar offen — erste Inhalte

**Voraussetzung für alles Weitere.** Solange kein Layer-Content da ist, kann weder Validierung noch Pilot noch Scoring starten.

Ablauf (aus Canvas #50):

1. **Rohmaterial sichten** — vorhandene Leitbild-, Pitch-, Positionierungs-Texte. Kein Workshop, kein Neu-Schreiben.
2. **Vier-Kanal-Destillation** — jede verwertbare Aussage genau einem Kanal zuordnen:
   - Ton: Satzlänge, Aktivsprache, Pronomen, Fachsprache ja/nein, Humor-Toleranz
   - Heuristiken: konkrete Entscheidungsregeln ("Geschwindigkeit vor Perfektion")
   - Negativ-Raum: was wir nie sagen — **stärkstes Signal**
   - Perspektiv-Anker: aus wessen Sicht spricht die Plattform?
3. **Token-Budget einhalten** — Ziel 150–200, erstes Runtergehen auf 250 ist okay
4. **Gegenprobe** — selber Prompt ohne vs. mit Layer: klingt es erkennbar anders?
5. **Negativ-Dokumentation** — was wurde bewusst weggelassen und warum?

Verantwortlich: **Martin Erren**, iterativ über 1–2 Wochen (~4–8h laut Canvas).

Deliverable: `layer-v1.0.0.json` → `php artisan layer:create … --from-file=…`.

---

## V1.1 — Lint-Mechanismus (~3–5 Tage)

**Problem:** Modul-Prompts könnten dem Layer widersprechen — das erzeugt Interferenz (siehe [Konzept](konzept#das-interference-problem)).

**Lösung:**

- `SemanticLayerLinter` Service, der Modul-Prompt-Strings gegen den `negativ_raum` und die Ton-Instruktionen prüft
- Command: `php artisan layer:lint [--module=okr]`
- Zweistufig:
  - **Stufe 1** — billiger Pattern-Match gegen Negativ-Raum-Einträge (Regex)
  - **Stufe 2** — LLM-basierter Konflikt-Check für Grenzfälle (nur wenn Stufe 1 unauffällig, aber Verdacht auf impliziten Konflikt)
- CI-Integration: PR mit geänderten Modul-Prompts läuft Lint, Block bei Konflikt

---

## V1.2 — Scoring-Framework (~5–7 Tage)

Qualitatives "klingt gut" reicht nicht. V1.2 bringt **vier quantitative Dimensionen** (aus Canvas #50):

| Dimension | Metrik | Ziel |
|---|---|---|
| 1. Ton-Konsistenz | Score 0–10 gegen Ton-Rubrik | ≥ 7 |
| 2. Heuristiken-Aktivierungsrate | % Outputs mit erkennbarer Heuristik | ≥ 70% |
| 3. Negativ-Raum-Verletzungsrate | % mit Pattern-Match gegen Verboten | ≤ 5% |
| 4. Cross-Modul-Konsistenz | Cosine-Similarity der Embeddings | ≥ 0.75 |

**Tech:**

- Neue Tabelle `semantic_layer_scores` (layer_id, version_id, dimension, score, measured_at, sample_count)
- `ScoreLayerJob` (Queue), Cron weekly
- Eingebauter Test-Prompt-Katalog pro Modul ("Formuliere ein OKR für Neukundengewinnung", …)
- **Definition of Done quantitativ**: alle 4 Dimensionen gleichzeitig ≥ Schwellenwert → production-ready

Messprotokoll:

- **T0** (Baseline) — alle Dimensionen **vor** Layer-Aktivierung messen
- **T1** (nach Pilot) — Zwei-Modul-Vergleich
- **T2** (nach Plattform-Rollout) — Cross-Modul-Score
- **Tk** (quartalsweise) — Zeitreihen-Vergleich für Drift-Detection

---

## V1.3 — Venture-Extensions live (~2–3 Tage Code, Inhalt parallel)

Der Resolver kann schon heute Extensions — jetzt kommen erste Daten:

- **RHEINGEDECK** — eigene Branchen-Realität, eigener Ton
- **ESSKULTUR.DIGITAL** — andere Zielgruppe, andere Sprache

**Risiko:** Wenn der Mutter-Layer einen MAJOR-Bump bekommt, müssen alle Extensions auf Kompatibilität geprüft werden.

**Maßnahmen:**

- Migrations-Command `layer:migrate --from=1.x --to=2.0` der alle abhängigen Extensions listet und Diff-Report erzeugt
- 48h-Parallelbetrieb alt vs. neu per Feature-Flag (Dual-Resolver)

---

## V2.0 — Production-Rollout & Admin-UI (~5–10 Tage)

**Admin-UI:**

- Vollwertiger Editor für Core-Layer (Livewire/Filament, Stil wie Rest der Plattform)
- Versionshistorie mit Diff-Ansicht
- Audit-Dashboard mit Filter nach Scope/Aktion/User
- Drift-Detection-Dashboard (Sample-Outputs zum Reviewen → "Würde ich das unterschreiben?")

**Rollout:**

- Alle Module in `enabled_modules` aufnehmen
- Status `production` plattformweit
- Kommunikation: interne Modul-Info — **kein** Change-Management, **kein** Feature-Launch (der Layer ist unsichtbare Infrastruktur)

---

## Was V1.0 explizit **nicht** macht

| | Warum / Wohin |
|---|---|
| ❌ Lint für Modul-Prompts | V1.1 |
| ❌ Quantitatives Scoring | V1.2 |
| ❌ Venture-Extensions mit realen Daten | V1.3 (Schema ist da) |
| ❌ Admin-UI | V2.0 — V1.0 nutzt Console + Debug-Panel |
| ❌ Plattformweiter Rollout | V2.0 — V1.0 ist Pilot auf OKR + Canvas |
| ❌ Parallele A/B-Tests | Canvas hat sequenziellen Rollout (Pre → Draft → Pilot → Prod), nicht parallel |
| ❌ Automatische Modul-Detection für Queue/Console | Aktuell nur Route-Name-Parsing; für Queue-Jobs greift nur `status=production` |

---

## Nächster konkreter Schritt

1. **Martin: Layer-Content erarbeiten** (Compression-Arbeit, ~4–8h über 1–2 Wochen)
2. **Layer laden + pilotieren auf OKR** (Console-Commands)
3. **Qualitativer Blind-Test** via OKR-Frontend: 5 neutrale Prompts ohne Layer archivieren, dann mit Layer wiederholen, Martin bewertet
4. **Go/No-Go für Canvas als zweites Pilot-Modul**
5. **V1.1 Lint-Mechanismus planen** — sobald mehr als zwei Module beteiligt sind, wird Konflikt-Prüfung Pflicht
