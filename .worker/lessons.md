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
