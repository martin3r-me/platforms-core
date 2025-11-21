# API-Infrastruktur Dokumentation

Diese Dokumentation beschreibt die API-Infrastruktur im Core, die von allen Modulen verwendet werden kann.

## Überblick

Der Core stellt eine vollständige API-Infrastruktur bereit, die es Modulen ermöglicht, ihre eigenen API-Endpunkte einfach zu erstellen und zu registrieren. Die Infrastruktur umfasst:

- **Base API Controller** mit standardisierten Response-Methoden
- **Authentifizierung** über Laravel Sanctum (Token-basiert)
- **Middleware** für API-Authentifizierung
- **ModuleRouter** Erweiterung für API-Routen
- **Standardisierte JSON-Responses**

## Authentifizierung

### Sanctum Token (Standard)

Die API verwendet Laravel Sanctum für Token-basierte Authentifizierung.

**Token erstellen (Command):**
```bash
# Via E-Mail
php artisan api:token:create --email=user@example.com --name="Datawarehouse Token" --show

# Via User ID
php artisan api:token:create --user-id=1 --name="Datawarehouse Token" --show
```

**Token erstellen (Programmatisch):**
```php
$user = User::find(1);
$token = $user->createToken('api-token')->plainTextToken;
```

**Token verwenden:**
```
Authorization: Bearer {token}
```

**Wichtig:** Der Token wird nur einmal angezeigt! Speichere ihn sicher (z.B. in `.env` des Datawarehouses).

### Header-basierte Authentifizierung (Fallback)

Für eingebettete Szenarien (z.B. Teams-Embedding) wird auch Header-basierte Authentifizierung unterstützt:

```
X-User-Email: user@example.com
```

## Base API Controller

Alle API-Controller sollten von `Platform\Core\Http\Controllers\ApiController` erben:

```php
<?php

namespace Platform\YourModule\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;

class YourController extends ApiController
{
    public function index()
    {
        $data = YourModel::all();
        return $this->success($data);
    }

    public function show($id)
    {
        $item = YourModel::find($id);
        
        if (!$item) {
            return $this->notFound();
        }

        return $this->success($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
        ]);

        $item = YourModel::create($validated);
        return $this->created($item);
    }
}
```

### Verfügbare Response-Methoden

- `success($data, $message, $statusCode)` - Erfolgreiche Response (200)
- `error($message, $errors, $statusCode)` - Fehler-Response (400)
- `validationError($errors, $message)` - Validierungsfehler (422)
- `notFound($message)` - Nicht gefunden (404)
- `unauthorized($message)` - Nicht autorisiert (401)
- `forbidden($message)` - Zugriff verweigert (403)
- `created($data, $message)` - Erstellt (201)
- `noContent()` - Kein Inhalt (204)
- `paginated($paginator, $message)` - Paginierte Response

### Response-Format

**Erfolgreiche Response:**
```json
{
    "success": true,
    "message": "Optional message",
    "data": { ... }
}
```

**Fehler-Response:**
```json
{
    "success": false,
    "message": "Fehlermeldung",
    "errors": { ... }
}
```

**Paginierte Response:**
```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "last_page": 10,
        "per_page": 15,
        "total": 150,
        "from": 1,
        "to": 15
    }
}
```

## API-Routen in Modulen registrieren

### 1. API-Controller erstellen

Erstelle einen Controller, der von `ApiController` erbt:

```php
<?php

namespace Platform\YourModule\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Illuminate\Http\Request;

class TaskController extends ApiController
{
    public function index()
    {
        $tasks = Task::paginate(15);
        return $this->paginated($tasks);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $task = Task::create($validated);
        return $this->created($task, 'Aufgabe erfolgreich erstellt');
    }
}
```

### 2. API-Routen-Datei erstellen

Erstelle `routes/api.php` in deinem Modul:

```php
<?php

use Illuminate\Support\Facades\Route;
use Platform\YourModule\Http\Controllers\Api\TaskController;

Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::get('/tasks/{id}', [TaskController::class, 'show']);
Route::put('/tasks/{id}', [TaskController::class, 'update']);
Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
```

### 3. Routen im ServiceProvider registrieren

In deinem `YourModuleServiceProvider`:

```php
public function boot(): void
{
    // ... andere Boot-Logik ...

    // API-Routen registrieren
    if (PlatformCore::getModule('yourmodule')) {
        ModuleRouter::apiGroup('yourmodule', function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }
}
```

### 4. Routen ohne Authentifizierung

Falls einige Routen öffentlich sein sollen:

```php
ModuleRouter::apiGroup('yourmodule', function () {
    $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
}, requireAuth: false);
```

Dann in der `routes/api.php` einzelne Routen mit Middleware schützen:

```php
Route::get('/public-endpoint', [Controller::class, 'public']);

Route::middleware('api.auth')->group(function () {
    Route::get('/protected-endpoint', [Controller::class, 'protected']);
});
```

## URL-Struktur

Die API-Routen werden automatisch mit dem Modul-Präfix versehen:

- **Path-Modus**: `/api/{modul-prefix}/endpoint`
  - Beispiel: `/api/planner/tasks`
  
- **Subdomain-Modus**: `{modul-prefix}.domain.com/api/endpoint`
  - Beispiel: `planner.example.com/api/tasks`

## Beispiel: Vollständiges Modul-Setup

### Controller

```php
<?php

namespace Platform\Planner\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Planner\Models\Task;
use Illuminate\Http\Request;

class TaskController extends ApiController
{
    public function index(Request $request)
    {
        $query = Task::query();

        // Filterung
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->paginate($request->get('per_page', 15));
        return $this->paginated($tasks);
    }

    public function show($id)
    {
        $task = Task::find($id);
        
        if (!$task) {
            return $this->notFound('Aufgabe nicht gefunden');
        }

        return $this->success($task);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $task = Task::create($validated);
        return $this->created($task, 'Aufgabe erfolgreich erstellt');
    }

    public function update(Request $request, $id)
    {
        $task = Task::find($id);
        
        if (!$task) {
            return $this->notFound('Aufgabe nicht gefunden');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $task->update($validated);
        return $this->success($task, 'Aufgabe erfolgreich aktualisiert');
    }

    public function destroy($id)
    {
        $task = Task::find($id);
        
        if (!$task) {
            return $this->notFound('Aufgabe nicht gefunden');
        }

        $task->delete();
        return $this->noContent();
    }
}
```

### Routes

```php
<?php

use Illuminate\Support\Facades\Route;
use Platform\Planner\Http\Controllers\Api\TaskController;

Route::apiResource('tasks', TaskController::class);
```

### ServiceProvider

```php
public function boot(): void
{
    // ... andere Boot-Logik ...

    if (PlatformCore::getModule('planner')) {
        // Web-Routen
        ModuleRouter::group('planner', function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        // API-Routen
        ModuleRouter::apiGroup('planner', function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }
}
```

## Best Practices

1. **Immer ApiController verwenden** - Für einheitliche Responses
2. **Validierung** - Verwende Laravel's Request Validation
3. **Fehlerbehandlung** - Nutze die bereitgestellten Error-Response-Methoden
4. **Pagination** - Verwende `paginated()` für Listen-Endpunkte
5. **Status-Codes** - Nutze die passenden HTTP-Status-Codes
6. **Dokumentation** - Dokumentiere deine API-Endpunkte (z.B. mit Swagger/OpenAPI)

## Testing

Beispiel für API-Tests:

```php
use Tests\TestCase;
use Platform\Core\Models\User;
use Laravel\Sanctum\Sanctum;

class TaskApiTest extends TestCase
{
    public function test_can_list_tasks()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/planner/tasks');
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['success', 'data', 'pagination']);
    }
}
```

