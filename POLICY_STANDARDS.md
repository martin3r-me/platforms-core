# Policy-Standardisierung

## Übersicht

Die Platform bietet standardisierte Policy-Basis-Klassen für alle Module, um Berechtigungslogik zu vereinheitlichen und Code-Duplikation zu vermeiden.

## Standard-Rollen

```php
use Platform\Core\Enums\StandardRole;

StandardRole::OWNER   // Vollzugriff
StandardRole::ADMIN   // Admin-Zugriff  
StandardRole::MEMBER  // Schreibzugriff
StandardRole::VIEWER  // Leszugriff
```

## Basis-Policy-Klassen

### 1. BasePolicy
Grundklasse mit Standard-Funktionen für alle Policies.

### 2. OwnerPolicy
Nur der Owner hat Zugriff auf die Ressource.

### 3. TeamPolicy  
Team-Mitglieder haben Zugriff auf Team-Ressourcen.

### 4. RolePolicy
Rollenbasierte Berechtigung mit Standard-Rollen.

## Policy-Traits

### HasOwnerAccess
```php
use Platform\Core\Traits\HasOwnerAccess;

class MyPolicy extends BasePolicy 
{
    use HasOwnerAccess;
    
    public function view(User $user, $model): bool 
    {
        return $this->ownerCanAccess($user, $model);
    }
}
```

### HasTeamAccess
```php
use Platform\Core\Traits\HasTeamAccess;

class MyPolicy extends BasePolicy 
{
    use HasTeamAccess;
    
    public function view(User $user, $model): bool 
    {
        return $this->teamCanAccess($user, $model);
    }
}
```

### HasRoleAccess
```php
use Platform\Core\Traits\HasRoleAccess;

class MyPolicy extends RolePolicy 
{
    use HasRoleAccess;
    
    protected function getUserRole(User $user, $model): ?string 
    {
        // Modulspezifische Rollen-Logik
        return $model->members()->where('user_id', $user->id)->first()?->role;
    }
}
```

## Verwendung in Modulen

### 1. Policy erstellen
```php
// In Modul: src/Policies/MyModelPolicy.php
namespace Platform\MyModule\Policies;

use Platform\Core\Policies\TeamPolicy;
use Platform\Core\Traits\HasRoleAccess;

class MyModelPolicy extends TeamPolicy 
{
    use HasRoleAccess;
    
    // Modulspezifische Logik überschreiben
    public function delete(User $user, $model): bool 
    {
        return $this->hasRole($user, $model, StandardRole::getAdminRoles());
    }
}
```

### 2. Policy registrieren
```php
// In Modul ServiceProvider
use Platform\Core\Support\PolicyRegistrar;

public function boot(): void 
{
    PolicyRegistrar::registerModulePolicies('my-module', [
        MyModel::class => MyModelPolicy::class,
    ]);
}
```

### 3. In Views verwenden
```blade
@can('view', $model)
    <div>Inhalt anzeigen</div>
@endcan

@can('update', $model)
    <button>Bearbeiten</button>
@endcan

@can('delete', $model)
    <button>Löschen</button>
@endcan
```

## Standard-Patterns

### Owner-Pattern
```php
class MyPolicy extends OwnerPolicy 
{
    // Nur Owner hat Zugriff
}
```

### Team-Pattern
```php
class MyPolicy extends TeamPolicy 
{
    // Team-Mitglieder haben Zugriff
}
```

### Role-Pattern
```php
class MyPolicy extends RolePolicy 
{
    protected function getUserRole(User $user, $model): ?string 
    {
        // Rollen-Logik implementieren
    }
}
```

## Vorteile

- ✅ **Einheitliche Rollen** across alle Module
- ✅ **Weniger Code-Duplikation**
- ✅ **Standardisierte Patterns**
- ✅ **Einfache Erweiterung** für Module
- ✅ **Konsistente Berechtigungslogik**
