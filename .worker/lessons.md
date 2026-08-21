# Package-Lektionen: platform-core

- Migrationen liegen in `database/migrations` (kein `Schema::table`-Wrapper nötig, aber
  einige alte Migrationen prüfen `Schema::hasTable` idempotent). `team_id` wird i.d.R. als
  `unsignedBigInteger('team_id')->index()` OHNE FK-Constraint angelegt (siehe core_embeddings),
  damit sqlite-Tests ohne echte teams-Zeilen laufen.
- Enums (string-backed) unter `src/Enums`, gespeichert als String-Spalte + Model-Cast
  (Plattform-Konvention: keine DB-`enum()`-Spalten).
- Package hatte KEIN `autoload-dev`/`database/factories`. Für Model-Factories: `newFactory()`
  im Model + Factory unter `Platform\Core\Database\Factories` (database/factories/) und
  `autoload-dev` in composer.json ergänzen (Namespaces `...\Tests\` und `...\Database\Factories\`).
- Package-Tests laufen via Orchestra Testbench mit sqlite `:memory:`; Muster:
  `getEnvironmentSetUp` setzt testing-Connection, `defineDatabaseMigrations` ruft
  `loadMigrationsFrom(.../database/migrations)`. Kein `vendor/` im Package → Tests lokal nicht
  ausführbar, nur `php -l` + Review.
- Model-Relationen zu Team/User referenzieren `\App\Models\Team` / `\App\Models\User`
  (nicht Platform\Core\Models\...), siehe CoreEntityLink.

- Context-Date-Times Dual-Write (generisch, config-getrieben): Fremd-Modelle (z.B.
  planner_tasks) werden in `core_context_date_times` gespiegelt, OHNE das Fremd-Package zu
  ändern. Whitelist steht in `config/core.php` (`context_date_times.sync`, gemerged als `core`),
  FQCN-Keys via `::class` (Klasse muss NICHT existieren – zur Laufzeit `class_exists`-geguarded).
  `CoreServiceProvider::boot()` hängt `ContextDateTimeObserver` an jede installierte Whitelist-
  Klasse, die das Trait `HasContextDateTimes` NICHT selbst nutzt (Trait registriert den Observer
  sonst doppelt → `class_uses_recursive`-Check). `source` = "migrated_from:<table>.<column>" ist
  der idempotente Identity-Key (Synchronizer::sync ist re-runbar). context_type = `getMorphClass()`.
- ACHTUNG Premissen prüfen: `planner_projects.starts_at/ends_at` existieren NICHT mehr – Projekt-
  Laufzeit liegt seit Migration `2026_06_01_..._migrate_dates_to_organization_time_periods` in
  `organization_time_periods` (Trait Organization\HasPlannedPeriod). Spalten-basierter Dual-Write
  ist dort nicht anwendbar. `planner_tasks.due_date` existiert (dateTime, nullable); `started_at`
  gibt es nicht.
- OKR-Zyklen: Model heißt `Platform\Okr\Models\Cycle` (NICHT OkrCycle). `okr_cycles.starts_at/ends_at`
  sind KEINE Spalten, sondern Accessoren (`getStartsAtAttribute`/`getEndsAtAttribute`), die an das
  verknüpfte `CycleTemplate` (`okr_cycle_templates.starts_at/ends_at`, date) delegieren. Der Dual-Write
  funktioniert trotzdem, weil `getAttribute()` Accessoren auflöst → Whitelist-Eintrag auf `Cycle::class`
  reicht (context = Cycle, team_id aus okr_cycles). `CycleTemplate` NICHT direkt observen (hat kein
  team_id → NOT-NULL-Verstoß in core_context_date_times). Gotcha beim Accessor-Pfad: ist die Relation
  auf der Instanz schon gecacht, spiegelt der Save-Observer den ALTEN Wert – relevante Änderung immer
  auf frisch geladener Instanz (oder `unsetRelation`) speichern.
- Cross-Modul-Provider-Pattern (analog CrmCompanyContactsProviderInterface,
  EventBookingProviderInterface, KeyResultMetricProvider): Interface in
  `src/Contracts/`, dazugehörige Plain-PHP-DTOs (keine Eloquent-Abhängigkeit)
  in einem eigenen Namespace neben Contracts (Vorbild: `Platform\Core\KeyResult\
  MetricRequest`/`MetricValue`), Multi-Provider-Registry (register/all/get,
  keyed by string) als Singleton in `src/Services/`, im `CoreServiceProvider::
  register()` neben den bestehenden Registry-Singletons eintragen. So bleibt
  Core der einzige gemeinsame Nenner zwischen zwei sonst unabhängigen Modulen.
- In dieser Sandbox ist KEIN `php`-Binary installiert (auch kein php8.x) —
  `php -l` ist hier grundsätzlich nicht ausführbar, nicht nur mangels vendor/.
  Syntax-Prüfung neuer/geänderter Dateien nur per sorgfältigem manuellem Review.
- Hatch-Intakes: Model heißt `Platform\Hatch\Models\HatchProjectIntake`, Tabelle
  `hatch_project_intakes` (NICHT `hatch_intakes`, wie ein Issue-Titel evtl. suggeriert –
  Migrationen im Nachbar-Repo `../platform-hatch` prüfen statt dem Issue-Text zu trauen).
  Spalten `started_at`/`completed_at` existieren (datetime, nullable); eine `deadline`-
  Spalte existiert (Stand 2026-08) NICHT. Für Context-Date-Times-Dual-Write reicht analog
  zu PlannerTask/Cycle ein reiner Whitelist-Eintrag in `config/core.php` – kein Trait-Einbau
  im Fremd-Package nötig (CoreServiceProvider hängt den Observer automatisch an, siehe
  `foreach (config('core.context_date_times.sync'))` in `CoreServiceProvider::boot()`).
  Feature-Tests für Fremd-Package-Models (Hatch/Planner/Okr) bilden die echte Tabelle über
  ein lokales Stub-Model + Test-Tabelle nach (siehe `ContextDateTimeSyncTest`,
  `ContextDateTimeCycleSyncTest`, `ContextDateTimeHatchIntakeSyncTest`), weil das
  Fremd-Package im Core-Testbench-Setup nicht installiert ist.
