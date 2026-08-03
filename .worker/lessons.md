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
