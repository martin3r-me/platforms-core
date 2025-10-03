# Core – Feldverschlüsselung & Hashing

Diese Bausteine stellen modulübergreifend sichere Feldverschlüsselung und Hashing bereit.

## Überblick

- Casts
  - `Platform\Core\Casts\EncryptedString` – verschlüsselt/entschlüsselt Strings transparent
  - `Platform\Core\Casts\EncryptedJson` – verschlüsselt/entschlüsselt JSON (Array)
- Trait
  - `Platform\Core\Traits\Encryptable` – richtet Casts ein und pflegt automatisch `<feld>_hash` beim Speichern
- Helper
  - `Platform\Core\Support\FieldHasher` – HMAC-SHA256 Hashing (mit optionalem Team-Salt), inkl. Key-Rotation
- Konfiguration
  - `config/security.php` – Schlüssel für Verschlüsselung/Hashing und Rotation

## Verwendung in Modellen

1) Felder definieren (z. B. in einem Modul-Model):

```php
use Platform\Core\Traits\Encryptable;

class CustomerSecret extends Model
{
    use Encryptable;

    protected array $encryptable = [
        'iban' => 'string',
        'api_token' => 'string',
        'meta' => 'json',
    ];
}
```

2) Migration im Modul:

```php
Schema::table('customers', function (Blueprint $table) {
    $table->text('iban')->nullable();
    $table->char('iban_hash', 64)->nullable()->index();
    $table->text('api_token')->nullable();
    $table->char('api_token_hash', 64)->nullable()->index();
    $table->longText('meta')->nullable();
});
```

3) Suche über Hash statt Klartext:

```php
use Platform\Core\Support\FieldHasher;

$teamSalt = (string) auth()->user()?->currentTeam?->id;
$hash = FieldHasher::hmacSha256($ibanImKlartext, $teamSalt);
$customer = CustomerSecret::where('iban_hash', $hash)->first();
```

## Schlüsselrotation

- `.env`:
  - `HASH_KEY=stable-hash-key` (optional; Fallback ist `APP_KEY`)
  - `PREVIOUS_HASH_KEYS=oldHashKey1,oldHashKey2`
  - `PREVIOUS_ENCRYPTION_KEYS=oldEncKey1,oldEncKey2`
- Verhalten:
  - Casts versuchen Entschlüsselung mit aktuellem `APP_KEY`, dann mit `PREVIOUS_ENCRYPTION_KEYS`.
  - `FieldHasher::matchesAny($value, $teamSalt, config('security.previous_hash_keys'))` liefert Hashes für aktuelle und alte Keys (für Migrations-/Vergleichslogik).

## Hinweise & Best Practices

- Verschlüsselte Felder als `text/longText` speichern.
- Für Indizes/Suche ausschließlich Hash-Spalten (`*_hash`) verwenden; niemals Klartext indizieren.
- Team-/Mandantensalt: Wenn möglich `team_id` als Salt nutzen, um Hashes teambezogen zu entkoppeln.
- Maskierung (UI/Logs): Bei Bedarf zusätzliche Helper ergänzen (z. B. IBAN-Maskierung).


