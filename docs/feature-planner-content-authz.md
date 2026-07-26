# Feature: Planner — Content-Autorisierung über den Graphen

**Ziel:** Der Planner nutzt den Autorisierungs-Graphen für Sichtbarkeit.
„Nichts pauschal" — man sieht nur, was man **erstellt hat** (Ersteller-Ownership)
oder über eine **Rolle im Baum erreicht**. Zwei Integrationspunkte:

- **Sidebar/Listen** → Query-Filter (`visibleTo()`), nicht Einzel-Checks.
- **Detail** (Projekt/Task aufrufen) → per-Objekt-Check (`may() || owns()`).

**Zielbild (B, entscheiden 2026-07-26):** Die alte Struktur kommt KOMPLETT raus —
User werden nicht mehr einem Projekt hinzugefügt (`projectUsers`), Policies greifen
nicht mehr darauf zurück. Zugriff = **Ersteller** ODER **graph-erreichbar (Rolle)**.
Weg dorthin (sicher, wegen geteiltem Code + Rollen-Abdeckung): hinter Flag
`authz.enforce_planner` bauen → Abdeckung sicherstellen → umschalten → dann Alt-Struktur löschen (#7).

Start reversibel/geflaggt (Shadow → planner-Enforce), damit vorher/nachher vergleichbar.

**Status:** In Arbeit · **Start:** 2026-07-26 12:20 · **Ende:** —

---

## Board

| # | Issue | Status | Start | Ende |
|---|-------|--------|-------|------|
| 1 | Resolver: `reachableEntityIds($user, $cap)` | ✅ Done | 2026-07-26 12:21 | 2026-07-26 12:23 |
| 2 | Query-Scope `visibleTo()` (wiederverwendbar, core) | ✅ Done | 2026-07-26 12:21 | 2026-07-26 12:23 |
| 3 | Planner-Sidebar: Projekte/Tasks filtern | Todo | — | — |
| 4 | Planner-Listen: Projekt-/Task-Queries filtern | Todo | — | — |
| 5 | Policy PlannerProject/PlannerTask (view/update/delete → may()||owns()) | ✅ Done | 2026-07-26 12:31 | 2026-07-26 12:39 |
| 6 | Flag `authz.enforce_planner` + Verifikation, dann scharf | Todo | — | — |
| 7 | Alt-Struktur entfernen: projectUsers/PlannerProjectUser + "User hinzufügen"-UI + ProjectRole (nach stabilem Enforce) | Todo | — | — |

---

## Issues (Detail)

### #1 — Resolver: `reachableEntityIds($user, $cap)`
Liefert die Menge der Entity-IDs, die der User mit mindestens `$cap` erreicht:
alle Descendants der Entities, an denen der User (bzw. sein Person-Entity) einen
capability-tragenden Grant hält (via `authz_scope_closure`). Basis für den
Query-Scope. Muss schnell sein (materialisierter Index).
`reachableEntityIds()` + `ownerColumn()` (aus `owns()` extrahiert) im AuthzResolver.
- Start: 2026-07-26 12:21 · Ende: 2026-07-26 12:23

### #2 — Query-Scope `visibleTo()` (core, wiederverwendbar)
Eloquent-Scope/Trait, den JEDES Modul auf Listen legt:
`WHERE ersteller_spalte = $user->id OR id IN (authz_resource_link.resource_id
für resource_type = <Model> und scope_id ∈ reachableEntityIds)`.
Eine Zeile pro Liste. Kein modul-spezifischer Authz-Code.
Umgesetzt als `VisibilityScope` + Builder-Macro `->authzVisibleTo($user, $cap)`
(NICHT `visibleTo` — kollidiert mit planner-eigenem lokalem Scope).
- Start: 2026-07-26 12:21 · Ende: 2026-07-26 12:23

### #3 — Planner-Sidebar filtern
`visibleTo()` auf die Projekt-/Task-Abfragen der Sidebar legen. Sichtbarster
Effekt: „ich sehe nur meine + meine Bereiche".
- Start: — · Ende: —

### #4 — Planner-Listen filtern
`visibleTo()` auf die Projekt-/Task-Listen (Index/Board) legen.
- Start: — · Ende: —

### #5 — Policy PlannerProject/PlannerTask
`view/update/delete` → Graph (`may()`) ODER Ersteller (`owns()`). Detail-Zugriff.
Umgesetzt hinter `config('authz.enforce_planner')`: Project-Policy `graphAllows()`
(read/write/manage); Task-Policy `projectGraphAllows()` in canAccess/canWrite/canAdmin
(delegiert Projekt-Tasks aufs Projekt; Owner/Zuständiger-Kurzschlüsse bleiben).
Flag aus → altes Verhalten unverändert.
- Start: 2026-07-26 12:31 · Ende: 2026-07-26 12:39

### #6 — Flag + Verifikation, dann scharf
`authz.enforce_planner` (Default aus). Shadow-Vergleich, vorher/nachher prüfen,
dann für planner scharf schalten. Reversibel. Voraussetzung: Rollen-Abdeckung
reicht (sonst verlieren nur-über-Mitgliedschaft-User Zugriff).
- Start: — · Ende: —

### #7 — Alt-Struktur entfernen (nach stabilem Enforce)
`PlannerProjectUser`/`projectUsers`-Tabelle + Relations, „User zum Projekt
hinzufügen"-UI, `ProjectRole`-Enum, die Membership-Zweige in den Policies/Scopes.
Erst wenn Enforce stabil + verifiziert (kein Rückweg-Bedarf).
- Start: — · Ende: —
