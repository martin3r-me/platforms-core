<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Schema\ModelSchemaRegistry;
use Illuminate\Filesystem\Filesystem;

class ModelAutoRegistrar
{
    protected ?string $modulesPath;

    public function __construct()
    {
        // Versuche verschiedene Pfade für die Module
        $possiblePaths = [
            __DIR__.'/../../modules',
            base_path('platform/modules'),
            __DIR__.'/../../../modules',
            base_path('vendor/martin3r'),
        ];
        
        \Log::info('ModelAutoRegistrar: Versuche Pfade: ' . implode(', ', $possiblePaths));
        
        foreach ($possiblePaths as $path) {
            $realPath = realpath($path);
            \Log::info("ModelAutoRegistrar: Prüfe Pfad: {$path} -> {$realPath}");
            
            if ($realPath && is_dir($realPath)) {
                $this->modulesPath = $realPath;
                \Log::info('ModelAutoRegistrar: Modules-Pfad gefunden: ' . $this->modulesPath);
                return;
            }
        }
        
        $this->modulesPath = null;
        \Log::info('ModelAutoRegistrar: Kein Modules-Pfad gefunden. Alle Pfade fehlgeschlagen.');
    }

    public function scanAndRegister(): void
    {
        if (!$this->modulesPath || !is_dir($this->modulesPath)) {
            \Log::info('ModelAutoRegistrar: Modules-Pfad nicht gefunden: ' . $this->modulesPath);
            return;
        }
        
        \Log::info('ModelAutoRegistrar: Verwende Modules-Pfad: ' . $this->modulesPath);

        $fs = new Filesystem();
        $modules = array_filter(glob($this->modulesPath.'/*'), 'is_dir');
        \Log::info('ModelAutoRegistrar: Scanne Module: ' . implode(', ', array_map('basename', $modules)));
        
        foreach ($modules as $moduleDir) {
            $moduleKey = basename($moduleDir);
            
            // Für Vendor-Module: platform-{module} -> {module}
            if (str_starts_with($moduleKey, 'platform-')) {
                $moduleKey = str_replace('platform-', '', $moduleKey);
            }
            
            $modelsDir = $moduleDir.'/src/Models';
            if (!is_dir($modelsDir)) {
                \Log::info("ModelAutoRegistrar: Kein Models-Verzeichnis für {$moduleKey}");
                continue;
            }
            \Log::info("ModelAutoRegistrar: Scanne Models in {$moduleKey}");
            
            foreach ($fs->allFiles($modelsDir) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $class = $this->classFromFile($file->getPathname(), $moduleKey, $modelsDir);
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

    protected function classFromFile(string $path, string $moduleKey, string $modelsDirBase): ?string
    {
        // PSR-4 Namespace inkl. Unterordner: Platform\\<Module>\\Models\\<SubPath>\\<Class>
        $relative = Str::after($path, rtrim($modelsDirBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
        $relative = str_replace(['/', '\\'], '\\', $relative);
        $relative = Str::replaceLast('.php', '', $relative);
        $nsModule = Str::studly($moduleKey);
        $class = 'Platform\\'.$nsModule.'\\Models\\'.$relative;
        \Log::info("ModelAutoRegistrar: Versuche Klasse {$class} für Datei {$path}");
        return $class;
    }

    protected function registerModelSchema(string $moduleKey, string $eloquentClass): void
    {
        // Tabellennamen ohne Instanziierung ermitteln
        $table = $this->getTableNameFromClass($eloquentClass);
        if (!$table) {
            \Log::info("ModelAutoRegistrar: Keine Tabelle für {$eloquentClass} gefunden");
            return;
        }
        
        if (!Schema::hasTable($table)) {
            \Log::info("ModelAutoRegistrar: Tabelle {$table} existiert nicht");
            return;
        }
        \Log::info("ModelAutoRegistrar: Tabelle {$table} existiert");
        \Log::info("ModelAutoRegistrar: Registriere Schema für {$table}");
        
        $columns = Schema::getColumnListing($table);
        $fields = array_values($columns);
        \Log::info("ModelAutoRegistrar: Spalten für {$table}: " . implode(', ', $fields));
        
        $selectable = array_values(array_slice($fields, 0, 6));
        $writable = $this->getFillableFromClass($eloquentClass);
        $sortable = array_values(array_intersect($fields, ['id','name','title','created_at','updated_at']));
        $filterable = array_values(array_intersect($fields, ['id','uuid','name','title','team_id','user_id','status','is_done']));
        $labelKey = in_array('name', $fields, true) ? 'name' : (in_array('title', $fields, true) ? 'title' : 'id');

        // Required-Felder per Doctrine DBAL (NOT NULL & kein Default, kein PK/AI)
        $required = [];
        try {
            $connection = \DB::connection();
            $schemaManager = method_exists($connection, 'getDoctrineSchemaManager')
                ? $connection->getDoctrineSchemaManager()
                : ($connection->getDoctrineSchemaManager ?? null);
            if ($schemaManager) {
                $doctrineTable = $schemaManager->listTableDetails($table);
                foreach ($doctrineTable->getColumns() as $name => $col) {
                    $notNull = !$col->getNotnull();
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

        // Relationen (belongsTo/hasMany/belongsToMany) per Reflection ermitteln - OHNE Instanziierung
        $relations = [];
        $foreignKeys = [];
        try {
            $ref = new \ReflectionClass($eloquentClass);
            foreach ($ref->getMethods() as $method) {
                if (!$method->isPublic() || $method->isStatic()) continue;
                if ($method->getNumberOfParameters() > 0) continue;
                if ($method->getDeclaringClass()->getName() !== $eloquentClass) continue;
                $name = $method->getName();
                if (in_array($name, ['getAttribute','setAttribute','newQuery','newModelQuery','newQueryWithoutScopes'], true)) continue;
                
                // Prüfe DocComment für Relation-Hinweise
                $docComment = $method->getDocComment();
                if ($docComment && (preg_match('/@return\\s+BelongsTo<([^>]+)>/i', $docComment, $matches) || preg_match('/@return\\s+\\\\?Illuminate\\\\\\\\Database\\\\\\\\Eloquent\\\\\\\\Relations\\\\\\\\BelongsTo<([^>]+)>/i', $docComment, $matches))) {
                    $targetClass = trim($matches[1]);
                    $foreignKey = null; // FK wird unten heuristisch gesetzt
                    
                    // Ableitung des targetModelKey aus dem Tabellennamen des Zielmodells
                    $targetTable = $this->getTableNameFromClass($targetClass);
                    if ($targetTable) {
                        $targetModuleKey = Str::before($targetTable, '_'); // Annahme: planner_tasks -> planner
                        $targetEntityKey = Str::after($targetTable, '_'); // Annahme: planner_tasks -> tasks
                        $targetModelKey = $targetModuleKey . '.' . $targetEntityKey;

                        // FK-Heuristik
                        $fkGuess = Str::snake($name).'_id';
                        if (in_array($fkGuess, $fields, true)) {
                            $foreignKey = $fkGuess;
                        }

                        $relations[$name] = [
                            'type' => 'belongsTo',
                            'target' => $targetModelKey,
                            'foreign_key' => $foreignKey,
                            'owner_key' => 'id',
                            'fields' => ['id', $labelKey],
                        ];
                        
                        if ($foreignKey) {
                            $foreignKeys[$foreignKey] = [
                                'references' => $targetModelKey,
                                'field' => 'id',
                                'label_key' => $labelKey,
                            ];
                        }
                    }
                }
                // HasMany
                if ($docComment && (preg_match('/@return\\s+HasMany<([^>]+)>/i', $docComment, $m2) || preg_match('/@return\\s+\\\\?Illuminate\\\\\\\\Database\\\\\\\\Eloquent\\\\\\\\Relations\\\\\\\\HasMany<([^>]+)>/i', $docComment, $m2))) {
                    $targetClass = trim($m2[1]);
                    $targetTable = $this->getTableNameFromClass($targetClass);
                    if ($targetTable) {
                        $relations[$name] = [
                            'type' => 'hasMany',
                            'target' => Str::before($targetTable, '_') . '.' . Str::after($targetTable, '_'),
                        ];
                    }
                }
                // BelongsToMany
                if ($docComment && (preg_match('/@return\\s+BelongsToMany<([^>]+)>/i', $docComment, $m3) || preg_match('/@return\\s+\\\\?Illuminate\\\\\\\\Database\\\\\\\\Eloquent\\\\\\\\Relations\\\\\\\\BelongsToMany<([^>]+)>/i', $docComment, $m3))) {
                    $targetClass = trim($m3[1]);
                    $targetTable = $this->getTableNameFromClass($targetClass);
                    if ($targetTable) {
                        $relations[$name] = [
                            'type' => 'belongsToMany',
                            'target' => Str::before($targetTable, '_') . '.' . Str::after($targetTable, '_'),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::info("ModelAutoRegistrar: Fehler beim Analysieren der Relationen für {$eloquentClass}: " . $e->getMessage());
        }

        // ModelKey ableiten (planner_tasks -> planner.tasks)
        $modelKey = Str::before($table, '_') . '.' . Str::after($table, '_');

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
    
    protected function getTableNameFromClass(string $eloquentClass): ?string
    {
        // Versuche Tabellennamen ohne Instanziierung zu ermitteln
        try {
            $reflection = new \ReflectionClass($eloquentClass);
            $defaultTable = Str::snake(Str::pluralStudly(class_basename($eloquentClass)));
            
            // Prüfe ob $table Property existiert
            if ($reflection->hasProperty('table')) {
                $tableProperty = $reflection->getProperty('table');
                $tableProperty->setAccessible(true);
                $table = $tableProperty->getDefaultValue();
                return $table ?: $defaultTable;
            }
            
            return $defaultTable;
        } catch (\Throwable $e) {
            \Log::info("ModelAutoRegistrar: Fehler beim Ermitteln der Tabelle für {$eloquentClass}: " . $e->getMessage());
            return null;
        }
    }
    
    protected function getFillableFromClass(string $eloquentClass): array
    {
        try {
            $reflection = new \ReflectionClass($eloquentClass);
            if ($reflection->hasProperty('fillable')) {
                $fillableProperty = $reflection->getProperty('fillable');
                $fillableProperty->setAccessible(true);
                return $fillableProperty->getDefaultValue() ?: [];
            }
            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}