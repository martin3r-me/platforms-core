<?php

namespace Platform\Core\Services;

use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Schema\ModelSchemaRegistry;

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
        foreach ($modules as $moduleDir) {
            $moduleKey = basename($moduleDir);
            $modelsDir = $moduleDir.'/src/Models';
            if (!is_dir($modelsDir)) continue;
            foreach ($fs->files($modelsDir) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $class = $this->classFromFile($file->getPathname(), $moduleKey);
                if (!$class || !class_exists($class)) continue;
                // Nur Eloquent Models
                if (!is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) continue;
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
            return;
        }
        $columns = Schema::getColumnListing($table);
        $fields = array_values($columns);
        $selectable = array_values(array_slice($fields, 0, 6));
        $writable = method_exists($model, 'getFillable') ? (array) $model->getFillable() : [];
        $sortable = array_values(array_intersect($fields, ['id','name','title','created_at','updated_at']));
        $filterable = array_values(array_intersect($fields, ['id','uuid','name','title','team_id','user_id','status','is_done']));
        $labelKey = in_array('name', $fields, true) ? 'name' : (in_array('title', $fields, true) ? 'title' : 'id');

        $entityKey = Str::snake(class_basename($eloquentClass));
        $modelKey = $moduleKey.'.'.$entityKey;

        ModelSchemaRegistry::register($modelKey, [
            'fields' => $fields,
            'filterable' => $filterable,
            'sortable' => $sortable,
            'selectable' => $selectable,
            'relations' => [],
            'required' => [],
            'writable' => $writable,
            'foreign_keys' => [],
            'meta' => [
                'eloquent' => $eloquentClass,
                'show_route' => null,
                'route_param' => null,
                'label_key' => $labelKey,
            ],
        });
    }
}

?>

