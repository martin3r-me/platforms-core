# DatawarehouseSourceProviderInterface

Cross-Modul-Contract, damit Module (z.B. `platform-hatch`) ihre Daten als
Stream-Quelle für `platform-datawarehouse` anbieten können, ohne dass die
beiden Module sich gegenseitig kennen — beide hängen nur von Core ab. Gleiches
Pattern wie `CrmCompanyContactsProviderInterface`, `CatalogListProviderInterface`
oder `EventBookingProviderInterface`.

## Bausteine

- `Platform\Core\Contracts\DatawarehouseSourceProviderInterface` — der Contract
  (`key()`, `label()`, `description()`, `teamScoped()`, `columns()`, `fetch()`).
- `Platform\Core\Datawarehouse\PullContext` / `PullResult` — schlanke Plain-PHP-DTOs
  (keine Eloquent-Abhängigkeit) für die paginierte Datenabfrage in `fetch()`.
- `Platform\Core\Services\DatawarehouseSourceRegistry` — Singleton-Registry
  (`register()`, `all()`, `get($key)`, `has($key)`).

## Verwendung

Ein Quell-Modul registriert sich im `boot()` seines ServiceProviders:

```php
resolve(\Platform\Core\Services\DatawarehouseSourceRegistry::class)
    ->register(new HatchSessionsStreamSource());
```

`platform-datawarehouse` liest den Katalog über dieselbe Registry aus
(`all()` für Discovery, `get($key)` beim Provisionieren/Pullen eines Streams)
und exponiert die registrierten Quellen als eigenen `PullProvider`.

## Offener Punkt

`PullContext`/`PullResult` sind hier bewusst dupliziert (statt eine Abhängigkeit
auf `platform-datawarehouse` einzugehen). Ihre Felder müssen inhaltlich mit dem
`PullProvider`-Contract in `platform-datawarehouse` abgestimmt und synchron
gehalten werden.
