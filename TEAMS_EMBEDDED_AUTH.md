# Microsoft Teams Embedded Authentication

## Problem

Microsoft Teams Tab Apps laufen über den Core mit Laravel Auth-Middleware, aber die Authentifizierung sollte über das Microsoft Teams SDK erfolgen, nicht über Laravel's Auth-System.

## Lösung

### 1. Neue Middleware: `TeamsSdkAuthMiddleware`

- **Datei**: `core/src/Middleware/TeamsSdkAuthMiddleware.php`
- **Alias**: `teams.sdk.auth`
- **Funktion**: Authentifiziert über Teams SDK Context ohne Laravel Auth

### 2. Helper-Klasse: `TeamsAuthHelper`

- **Datei**: `core/src/Helpers/TeamsAuthHelper.php`
- **Funktionen**:
  - `getTeamsUser(Request)` - Holt Teams User-Info
  - `getTeamsContext(Request)` - Holt Teams Context
  - `isTeamsRequest(Request)` - Prüft ob Request von Teams kommt
  - `getTeamsUserEmail(Request)` - Holt User-Email
  - `getTeamsUserName(Request)` - Holt User-Name

### 3. Route-Konfiguration

```php
// Alte Konfiguration (mit Laravel Auth)
Route::get('/embedded/example', function() {
    // ...
})->middleware(['teams.sso'])->withoutMiddleware([FrameGuard::class]);

// Neue Konfiguration (ohne Laravel Auth)
Route::get('/embedded/example', function() {
    // ...
})->middleware(['teams.sdk.auth'])->withoutMiddleware([
    FrameGuard::class, 
    'auth', 
    'detect.module.guard', 
    'check.module.permission'
]);
```

### 4. Verwendung in Livewire-Komponenten

```php
use Platform\Core\Helpers\TeamsAuthHelper;

class EmbeddedComponent extends Component
{
    public function someAction()
    {
        // Teams User-Info aus Request holen
        $teamsUser = TeamsAuthHelper::getTeamsUser(request());
        
        if (!$teamsUser) {
            // Fehlerbehandlung
            return;
        }

        // User aus Teams Context finden oder erstellen
        $user = $this->findOrCreateUserFromTeams($teamsUser);
        
        // Weitere Logik...
    }

    private function findOrCreateUserFromTeams(array $teamsUser)
    {
        $userModelClass = config('auth.providers.users.model');
        
        $user = $userModelClass::query()
            ->where('email', $teamsUser['email'])
            ->orWhere('azure_id', $teamsUser['id'])
            ->first();

        if (!$user) {
            $user = new $userModelClass();
            $user->email = $teamsUser['email'];
            $user->name = $teamsUser['name'] ?? $teamsUser['email'];
            $user->azure_id = $teamsUser['id'] ?? null;
            $user->save();
            
            \Platform\Core\PlatformCore::createPersonalTeamFor($user);
        }

        return $user;
    }
}
```

## Für andere Module

### 1. Routes anpassen

Alle embedded Routes sollten die neue Middleware verwenden:

```php
// In module/routes/web.php
Route::get('/embedded/module/example', function() {
    // ...
})->middleware(['teams.sdk.auth'])->withoutMiddleware([
    FrameGuard::class, 
    'auth', 
    'detect.module.guard', 
    'check.module.permission'
]);
```

### 2. Livewire-Komponenten anpassen

- `Auth::user()` durch `TeamsAuthHelper::getTeamsUser(request())` ersetzen
- User-Finding/Erstellung implementieren
- Teams Context für weitere Logik nutzen

### 3. Views anpassen

```php
// In Blade-Templates
@php
    $teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser(request());
@endphp

@if($teamsUser)
    Hallo, {{ $teamsUser['name'] ?? $teamsUser['email'] }}
@else
    Nicht authentifiziert
@endif
```

## Vorteile

1. **Keine Laravel Auth-Abhängigkeit** - Teams SDK übernimmt Authentifizierung
2. **Bessere Performance** - Weniger Middleware-Stack
3. **Teams-native** - Nutzt Teams Context direkt
4. **Flexibel** - Kann für alle Module verwendet werden
5. **Sicher** - AuthAccessPolicy wird weiterhin geprüft

## Migration

1. Neue Middleware ist bereits registriert
2. Planner-Modul ist bereits migriert
3. Andere Module können schrittweise migriert werden
4. Alte `teams.sso` Middleware bleibt für normale Web-Routes
