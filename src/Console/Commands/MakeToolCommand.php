<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

/**
 * Artisan Command zum Generieren von Tools
 * 
 * Usage: php artisan make:tool ModuleName.ToolName
 */
class MakeToolCommand extends Command
{
    protected $signature = 'make:tool {name : Der Name des Tools (z.B. planner.projects.create)} 
                            {--module= : Modul-Name (optional, wird aus name extrahiert)}
                            {--description= : Beschreibung des Tools}
                            {--dependencies : Tool hat Dependencies}
                            {--metadata : Tool hat Metadata}';

    protected $description = 'Erstellt ein neues Tool mit vollständiger Struktur';

    public function handle(): int
    {
        $name = $this->argument('name');
        
        // Parse Name (z.B. "planner.projects.create" -> Module: planner, Tool: ProjectsCreate)
        $parts = explode('.', $name);
        if (count($parts) < 2) {
            $this->error("Tool-Name muss im Format 'module.tool.name' sein (z.B. 'planner.projects.create')");
            return 1;
        }

        $moduleName = $this->option('module') ?: $parts[0];
        $toolNameParts = array_slice($parts, 1);
        $toolClassName = Str::studly(implode('', $toolNameParts)) . 'Tool';
        $toolFileName = $toolClassName . '.php';

        // Bestimme Pfad
        $modulePath = base_path("modules/{$moduleName}");
        if (!File::exists($modulePath)) {
            $this->error("Modul '{$moduleName}' existiert nicht. Erstelle es zuerst.");
            return 1;
        }

        $toolsPath = "{$modulePath}/src/Tools";
        if (!File::exists($toolsPath)) {
            File::makeDirectory($toolsPath, 0755, true);
        }

        $toolPath = "{$toolsPath}/{$toolFileName}";

        if (File::exists($toolPath)) {
            if (!$this->confirm("Tool existiert bereits. Überschreiben?")) {
                return 0;
            }
        }

        // Generiere Tool-Code
        $stub = $this->getStub();
        $code = $this->replacePlaceholders($stub, [
            'NAMESPACE' => "Platform\\{$moduleName}\\Tools",
            'CLASS_NAME' => $toolClassName,
            'TOOL_NAME' => $name,
            'DESCRIPTION' => $this->option('description') ?: "Tool: {$name}",
            'DEPENDENCIES' => $this->option('dependencies') ? $this->getDependenciesCode() : '',
            'METADATA' => $this->option('metadata') ? $this->getMetadataCode() : '',
        ]);

        File::put($toolPath, $code);

        $this->info("✅ Tool erstellt: {$toolPath}");
        $this->line("📝 Tool-Name: {$name}");
        $this->line("📦 Klasse: {$toolClassName}");
        
        if ($this->option('dependencies')) {
            $this->line("🔗 Dependencies: Implementiert");
        }
        if ($this->option('metadata')) {
            $this->line("📋 Metadata: Implementiert");
        }

        return 0;
    }

    private function getStub(): string
    {
        return <<<'STUB'
<?php

namespace NAMESPACE;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * TOOL_NAME
 * 
 * DESCRIPTION
 */
class CLASS_NAME implements ToolContractDEPENDENCIESMETADATA
{
    /**
     * Tool-Name (unique identifier)
     */
    public function getName(): string
    {
        return 'TOOL_NAME';
    }

    /**
     * Tool-Beschreibung
     */
    public function getDescription(): string
    {
        return 'DESCRIPTION';
    }

    /**
     * JSON Schema für Tool-Parameter
     */
    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                // TODO: Füge Parameter hinzu
            ],
            'required' => [
                // TODO: Füge required fields hinzu
            ],
        ];
    }

    /**
     * Führt das Tool aus
     */
    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        // TODO: Implementiere Tool-Logik
        
        return ToolResult::success([
            'message' => 'Tool erfolgreich ausgeführt',
        ]);
    }
}
STUB;
    }

    private function replacePlaceholders(string $stub, array $replacements): string
    {
        $code = $stub;
        foreach ($replacements as $key => $value) {
            $code = str_replace($key, $value, $code);
        }
        return $code;
    }

    private function getDependenciesCode(): string
    {
        return ', \Platform\Core\Contracts\ToolDependencyContract';
    }

    private function getMetadataCode(): string
    {
        return ', \Platform\Core\Contracts\ToolMetadataContract';
    }
}

