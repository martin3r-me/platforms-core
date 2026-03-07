<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\Documents\DocumentTemplateRegistry;

class ListDocumentTemplatesTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.templates.GET';
    }

    public function getDescription(): string
    {
        return 'Listet verfügbare Dokument-Templates für das aktuelle Team auf. '
            . 'Templates sind Layout-Wrapper für PDF-Dokumente — du generierst den HTML-Body in data.html_content, das Template kümmert sich um CSS, Seitenformat und Branding. '
            . 'Haupttemplates: "report" (generischer Bericht), "letter" (Brief mit Absender/Empfänger), "table-report" (datenintensiv mit KPI-Karten und Tabellen). '
            . 'Nutze include_schema:true um die verfügbaren Felder und CSS-Klassen eines Templates zu sehen. '
            . 'Danach: core.documents.CREATE um ein Dokument zu erstellen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'key' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Template-Key (z.B. "report", "letter", "table-report")',
                ],
                'include_schema' => [
                    'type' => 'boolean',
                    'description' => 'Ob das JSON-Schema (verfügbare Felder, CSS-Klassen) der Templates mit ausgegeben werden soll (Standard: false). Setze auf true um zu sehen, welche data-Felder ein Template erwartet.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $registry = app(DocumentTemplateRegistry::class);
            $includeSchema = (bool) ($arguments['include_schema'] ?? false);

            // Filter by key or list all
            if (!empty($arguments['key'])) {
                $template = $registry->resolve($arguments['key'], $team->id);
                if (!$template) {
                    return ToolResult::error("Template '{$arguments['key']}' nicht gefunden.", 'NOT_FOUND');
                }

                return ToolResult::success([
                    'template' => $this->formatTemplate($template, $includeSchema),
                ]);
            }

            $templates = $registry->listForTeam($team->id);

            return ToolResult::success([
                'templates' => $templates->map(fn($t) => $this->formatTemplate($t, $includeSchema))->toArray(),
                'total' => $templates->count(),
                'hint' => 'Nutze core.documents.CREATE mit template_key und data.html_content um ein Dokument als PDF zu generieren.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Templates: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function formatTemplate($template, bool $includeSchema): array
    {
        $result = [
            'id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'description' => $template->description,
            'is_system_default' => $template->is_system_default,
            'has_blade_view' => !empty($template->blade_view),
            'has_db_content' => !empty($template->content),
        ];

        if ($template->default_data) {
            $result['default_data'] = $template->default_data;
        }

        if ($includeSchema && $template->schema) {
            $result['schema'] = $template->schema;
        }

        return $result;
    }
}
