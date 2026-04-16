---
title: Konzept
order: 2
---

# Warum der Layer wirkt

Diese Seite fasst die theoretische Fundierung aus Canvas #50 in die technisch relevanten Punkte zusammen. Wer die Implementierung versteht, aber das Warum nicht, tunet am falschen Hebel.

---

## Der Attention-Mechanismus macht frühe Tokens mächtiger

LLMs sind Wahrscheinlichkeitsmaschinen: *Gegeben alles was vorher steht, was ist das wahrscheinlichste nächste Token?* Der entscheidende Hebel ist der **Kontext** — und der ist gestaltbar.

Durch den kausalen Attention-Mechanismus erhalten **frühe Tokens** im Kontext-Fenster systematisch **höhere Gewichtung**. Ein semantisch kohärenter Block **ganz am Anfang** setzt einen statistischen Prior, der die gesamte Wahrscheinlichkeitsverteilung aller nachfolgenden Outputs verschiebt.

Formal:

```
P(output | semantic_layer + modul_prompt + user_input)
   ≠
P(output | modul_prompt + user_input)
```

Das ist keine Meinung — das ist beobachtbares, messbares Modellverhalten.

**Konsequenz für die Architektur:** Der Layer-Block muss **vor** dem Modul-Prompt im Systemprompt stehen. Das garantiert [CoreContextTool.php:59-61](../../src/Tools/CoreContextTool.php):

```php
$systemPrompt = $layerBlock
    ? trim($layerBlock . "\n\n" . $baseInstruction)
    : $baseInstruction;
```

---

## Warum Kompression — und warum mehr nicht besser ist

Attention ist keine unendliche Ressource. Zu langer Layer = **Attention-Dilution**: die Gewichtung verteilt sich, einzelne Instruktionen verlieren Einfluss. Zu kurzer Layer = **Prior zu schwach**: das Modell fällt auf seinen Trainings-Default zurück.

Empirisches Optimum: **150–200 Tokens** (soft 80–250).

Das ist im Code als Budget-Check verankert:

```
// src/SemanticLayer/Schema/LayerSchemaValidator.php
const TOKEN_BUDGET_MIN = 80;
const TOKEN_BUDGET_MAX = 250;
```

Der Validator lehnt Layer außerhalb des Budgets nicht ab (soft fail), er **warnt**. Die Entscheidung bleibt bei Martin.

---

## Verhaltens-Instruktion > Werte-Deklaration

Das ist der Kern-Insight des Projekts:

| Formulierung | Wirkung |
|---|---|
| "Wir sind innovativ" | Aktiviert diffuses Konzept-Cluster — Modell weiß nicht, was tun |
| "Schreibe kurze Sätze, kein Passiv, keine Corporate-Begriffe" | Aktiviert enge Verhaltens-Muster — fließt direkt in Output-Stil |
| "Nie Corporate-Sprache" | **Stärkste** Form — Ausschluss-Logik ist im Transformer besonders scharf enkodiert |

Deshalb hat das Schema **genau diese vier Kanäle**, bewusst verbots-lastig:

- `perspektive` — aus wessen Sicht spricht die Plattform? (1 String)
- `ton[]` — wie klingt eine Antwort konkret? (max 12 Items)
- `heuristiken[]` — was gilt im Zweifel? (max 12 Items)
- `negativ_raum[]` — was sagen wir nie? (max 12 Items)

Keine freien Felder, keine generischen `values[]`. Schema-Validierung läuft beim `saving`-Event — jede Abweichung wird hart abgelehnt.

---

## Das Interference-Problem

Der häufigste Grund, warum Semantic-Layer-Projekte scheitern: **zwei Instruktionen kämpfen im selben Kontextfenster gegeneinander**.

Konkrete Konflikt-Szenarien:
- **Ton-Konflikt:** Helpdesk-Modul sagt "formell und ausführlich" — Layer sagt "direkt, kurz". Modell kompromittiert inkonsistent.
- **Rollen-Konflikt:** CRM-Modul definiert "Assistent" — Layer verankert "Unternehmer". Zwei Identitäten im selben Output.
- **Instruktions-Kollision:** Mehrere widersprechende Direktiven → statistische Mittelung → die schlechteste aller Welten.

**Lösung — explizite Prompt-Hierarchie:**

1. **System Identity (Semantic Layer)** — unveränderlich, immer zuerst. Einzige Quelle für Identität.
2. **Task Context (Modul-Prompt)** — *was* getan wird. Darf Ebene 1 **ergänzen**, niemals widersprechen.
3. **User Input** — wirkt innerhalb der Grenzen von 1+2.

In V1.0 bleibt Ebene 2 noch menschlich überprüft. V1.1 bringt einen [Lint-Mechanismus](roadmap) der Modul-Prompts automatisch gegen den `negativ_raum` checkt.

---

## Scope-Modell: inherit + extend, never override

Eine einzige Plattform trägt mehrere Ventures (BHG Digital, RHEINGEDECK, ESSKULTUR.DIGITAL, …). Die richtige Architektur ist kein Layer-pro-Venture (→ Drift) und kein One-Size-Fits-All (→ kleinster gemeinsamer Nenner).

**Drei Ebenen:**

| Ebene | Inhalt | Änderbar durch |
|---|---|---|
| 1 — Core Identity | BHG Digital Mutter-Layer | Martin Erren (Admin) |
| 2 — Venture Extension | Venture-spezifischer Ton + Heuristiken | Autorisierte GF des Ventures |
| 3 — Modul-Kontext | Task-spezifische Modul-Prompts | Max Walter / Sebastian Haustein, Lint-Check |

Der Resolver löst die Ebenen in ein **einzelnes `ResolvedLayer`-Objekt** auf:

- `perspektive` — Extension überschreibt Core (wenn gesetzt), sonst Core
- `ton[]`, `heuristiken[]`, `negativ_raum[]` — Append + Deduplizierung (case-insensitive)

Nur das resolved-Objekt verlässt das System. Kein Consumer arbeitet mit Roh-Ebenen.

---

## Drift — das gefährlichste Langzeit-Risiko

Ein Layer, der nicht mehr stimmt, ist **aktiv schädlicher als kein Layer**. Er injiziert falsche Identität plattformweit, ohne dass jemand den Grund benennen kann — nur ein diffuses Unbehagen.

**Quartals-Routine** (aus Canvas #50):

> Martin liest fünf Layer-generierte Outputs aus verschiedenen Modulen und beantwortet **eine einzige Frage**: *Würde ich das so unterschreiben?*

Antwort "nicht mehr" = System funktioniert, nicht Versagen. Folge: Minor- oder Major-Revision (siehe [Versionierung](versionierung)).

Technisch gestützt: jede Änderung erzeugt einen Eintrag in `semantic_layer_audit` mit Diff + User-ID + Timestamp. Die Audit-Chain ist unveränderbar.
