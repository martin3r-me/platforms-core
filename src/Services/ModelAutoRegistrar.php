<?php

namespace Platform\Core\Services;

use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Schema\ModelSchemaRegistry;
use ReflectionClass;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelAutoRegistrar
{
    protected string $modulesPath;

    public function __construct(?string $modulesPath = null)
    {
        $this->modulesPath = $modulesPath ?: realpath(__DIR__.'/../../modules');
    }

    public function scanAndRegister(): void
    {
        if (!$this->modulesPath || !is_dir($this->modulesPath)) {
            return;
        }
        $fs = new Filesystem();
        $modules = array_filter(glob($this->modulesPath.'/*'), 'is_dir');
        \Log::info('ModelAutoRegistrar: Scanne Module: ' . implode(', ', array_map('basename', $modules)));
        foreach ($modules as $moduleDir) {
            $moduleKey = basename($moduleDir);
            $modelsDir = $moduleDir.'/src/Models';
            if (!is_dir($modelsDir)) {
                \Log::info("ModelAutoRegistrar: Kein Models-Verzeichnis für {$moduleKey}");
                continue;
            }
            \Log::info("ModelAutoRegistrar: Scanne Models in {$moduleKey}");
            foreach ($fs->files($modelsDir) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $class = $this->classFromFile($file->getPathname(), $moduleKey);
                if (!$class) {
                    \Log::info("ModelAutoRegistrar: Keine Klasse für {$file->getPathname()}");
                    continue;
                }
                if (!class_exists($class)) {
                    \Log::info("ModelAutoRegistrar: Klasse {$class} existiert nicht");
                    continue;
                }
                // Nur Eloquent Models
                if (!is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
                    \Log::info("ModelAutoRegistrar: {$class} ist kein Eloquent Model");
                    continue;
                }
                \Log::info("ModelAutoRegistrar: Registriere {$class}");
                $this->registerModelSchema($moduleKey, $class);
            }
        }
    }

    protected function classFromFile(string $path, string $moduleKey): ?string
    {
        // Erwartet PSR-4 Namespace: Platform\\<Module>\\Models\\<Class>
        $fileName = pathinfo($path, PATHINFO_FILENAME);
        $nsModule = Str::studly($moduleKey);
        $class = 'Platform\\'.$nsModule.'\\Models\\'.$fileName;
        \Log::info("ModelAutoRegistrar: Versuche Klasse {$class} für Datei {$path}");
        return $class;
    }

    protected function registerModelSchema(string $moduleKey, string $eloquentClass): void
    {
        try {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = new $eloquentClass();
        } catch (\Throwable $e) {
            return;
        }
        $table = $model->getTable();
        if (!Schema::hasTable($table)) {
            \Log::info("ModelAutoRegistrar: Tabelle {$table} existiert nicht");
            return;
        }
        \Log::info("ModelAutoRegistrar: Registriere Schema für {$table}");
        // Für ModelKey-Ableitung (planner_tasks -> planner.tasks)
        $tablePrefix = $moduleKey.'_';
        $columns = Schema::getColumnListing($table);
        $fields = array_values($columns);
        $selectable = array_values(array_slice($fields, 0, 6));
        $writable = method_exists($model, 'getFillable') ? (array) $model->getFillable() : [];
        $sortable = array_values(array_intersect($fields, ['id','name','title','created_at','updated_at']));
        $filterable = array_values(array_intersect($fields, ['id','uuid','name','title','team_id','user_id','status','is_done']));
        $labelKey = in_array('name', $fields, true) ? 'name' : (in_array('title', $fields, true) ? 'title' : 'id');

        // Required-Felder per Doctrine DBAL (NOT NULL & kein Default, kein PK/AI)
        $required = [];
        try {
            $connection = $model->getConnection();
            $schemaManager = method_exists($connection, 'getDoctrineSchemaManager')
                ? $connection->getDoctrineSchemaManager()
                : ($connection->getDoctrineSchemaManager ?? null);
            if ($schemaManager) {
                $doctrineTable = $schemaManager->listTableDetails($table);
                foreach ($doctrineTable->getColumns() as $col) {
                    $name = $col->getName();
                    if ($name === 'id' || $name === 'created_at' || $name === 'updated_at') continue;
                    if ($doctrineTable->hasPrimaryKey() && in_array($name, (array) ($doctrineTable->getPrimaryKey()?->getColumns() ?? []), true)) continue;
                    if ($col->getAutoincrement()) continue;
                    $notNull = $col->getNotnull();
                    $hasDefault = $col->getDefault() !== null;
                    if ($notNull && !$hasDefault) {
                        $required[] = $name;
                    }
                }
                $required = array_values(array_intersect($required, $fields));
            }
        } catch (\Throwable $e) {
            $required = [];
        }

        // Relationen (belongsTo) per Reflection ermitteln
        $relations = [];
        $foreignKeys = [];
        try {
            $ref = new ReflectionClass($eloquentClass);
            foreach ($ref->getMethods() as $method) {
                if (!$method->isPublic() || $method->isStatic()) continue;
                if ($method->getNumberOfParameters() > 0) continue;
                if ($method->getDeclaringClass()->getName() !== $eloquentClass) continue;
                $name = $method->getName();
                if (in_array($name, ['getAttribute','setAttribute','newQuery','newModelQuery','newQueryWithoutScopes'], true)) continue;
                try {
                    $rel = $model->$name();
                } catch (\Throwable $e) {
                    continue;
                }
                if ($rel instanceof Relation && $rel instanceof BelongsTo) {
                    $fk = method_exists($rel, 'getForeignKeyName') ? $rel->getForeignKeyName() : null;
                    $ownerKey = method_exists($rel, 'getOwnerKeyName') ? $rel->getOwnerKeyName() : null;
                    $targetClass = get_class($rel->getRelated());
                    $targetModelKey = null;
                    $targetLabel = 'id';
                    try {
                        /** @var \Illuminate\Database\Eloquent\Model $targetModel */
                        $targetModel = new $targetClass();
                        $targetTable = $targetModel->getTable();
                        $targetEntity = Str::startsWith($targetTable, $tablePrefix)
                            ? Str::after($targetTable, $tablePrefix)
                            : Str::snake(class_basename($targetClass));
                        $targetModelKey = $moduleKey.'.'.$targetEntity;
                        $targetFields = Schema::hasTable($targetTable) ? Schema::getColumnListing($targetTable) : [];
                        $targetLabel = in_array('name', $targetFields, true) ? 'name' : (in_array('title', $targetFields, true) ? 'title' : 'id');
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    $relations[$name] = [
                        'type' => 'belongsTo',
                        'target' => $targetClass,
                        'foreign_key' => $fk,
                        'owner_key' => $ownerKey,
                        'fields' => ['id', (in_array('name', $fields, true) ? 'name' : (in_array('title', $fields, true) ? 'title' : 'id'))],
                    ];
                    if ($fk) {
                        $foreignKeys[$fk] = [
                            // wichtig: als ModelKey referenzieren, nicht Klassenname
                            'references' => $targetModelKey,
                            'label_key' => $targetLabel,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // EntityKey bevorzugt aus Tabellenname ableiten
        $entityKey = Str::startsWith($table, $tablePrefix)
            ? Str::after($table, $tablePrefix)
            : Str::snake(class_basename($eloquentClass));
        $modelKey = $moduleKey.'.'.$entityKey;

        ModelSchemaRegistry::register($modelKey, [
            'fields' => $fields,
            'filterable' => $filterable,
            'sortable' => $sortable,
            'selectable' => $selectable,
            'relations' => $relations,
            'required' => $required,
            'writable' => $writable,
            'foreign_keys' => $foreignKeys,
            'meta' => [
                'eloquent' => $eloquentClass,
                'show_route' => null,
                'route_param' => null,
                'label_key' => $labelKey,
            ],
        ]);
    }
}

?>

