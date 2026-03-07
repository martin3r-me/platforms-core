<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Jobs\RenderDocumentJob;
use Platform\Core\Services\Documents\DocumentService;

class CreateDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.CREATE';
    }

    public function getDescription(): string
    {
        return 'Erstellt ein Dokument und rendert es als PDF. '
            . 'Du generierst den HTML-Body und übergibst ihn in data.html_content — das Template kümmert sich um Layout und Styling. '
            . "\n\n"
            . 'WORKFLOW: '
            . '1) Optional: core.documents.templates.GET (include_schema:true) um verfügbare Felder/CSS-Klassen zu sehen. '
            . '2) core.documents.CREATE mit template_key + data. '
            . '3) core.documents.GET um Status und Download-URL abzurufen (Rendering ist asynchron). '
            . "\n\n"
            . 'TEMPLATES: '
            . '"report" — Generischer Bericht. Felder: html_content (required), subtitle, date, author. '
            . '"letter" — Brief. Felder: html_content (required), sender, recipient, date, subject, closing, signature. '
            . '"table-report" — Daten-Report. Felder: html_content (required), subtitle, date, author. CSS-Klassen: .kpi-grid>.kpi-card>.kpi-value+.kpi-label, table (auto-styled), .total-row. '
            . "\n\n"
            . 'HTML-CONTENT TIPPS: '
            . 'Nutze semantisches HTML: h1-h3, p, ul/ol, table (th+td), hr. '
            . 'Tabellen werden automatisch gestylt. Für Zahlenkolumnen: class="number" auf th/td. '
            . 'Seitenumbruch: <div class="page-break"></div>. ';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'template_key' => [
                    'type' => 'string',
                    'description' => 'Template-Key: "report", "letter", "table-report" oder ein custom Key.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Titel des Dokuments — wird als Dateiname für den PDF-Download verwendet.',
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'Template-Daten. Wichtigstes Feld: html_content (der HTML-Body den du generierst). Weitere Felder je nach Template (siehe Schema).',
                    'properties' => [
                        'html_content' => [
                            'type' => 'string',
                            'description' => 'Der HTML-Body des Dokuments. Du generierst diesen Inhalt basierend auf den Daten.',
                        ],
                    ],
                ],
                'auto_render' => [
                    'type' => 'boolean',
                    'description' => 'Standard: true. PDF wird sofort asynchron gerendert. Bei false nur Draft (kann später mit core.documents.EXPORT gerendert werden).',
                ],
                'renderer_options' => [
                    'type' => 'object',
                    'description' => 'Optional: PDF-Optionen die Template-Defaults überschreiben.',
                    'properties' => [
                        'format' => ['type' => 'string', 'description' => 'Papierformat: A4 (default), A3, Letter, Legal'],
                        'landscape' => ['type' => 'boolean', 'description' => 'Querformat'],
                        'margin_top' => ['type' => 'integer', 'description' => 'Rand oben in mm'],
                        'margin_bottom' => ['type' => 'integer', 'description' => 'Rand unten in mm'],
                        'margin_left' => ['type' => 'integer', 'description' => 'Rand links in mm'],
                        'margin_right' => ['type' => 'integer', 'description' => 'Rand rechts in mm'],
                    ],
                ],
            ],
            'required' => ['template_key', 'title', 'data'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $templateKey = $arguments['template_key'];
            $title = $arguments['title'];
            $data = $arguments['data'] ?? [];
            $autoRender = $arguments['auto_render'] ?? true;
            $rendererOptions = $arguments['renderer_options'] ?? [];

            $service = app(DocumentService::class);

            // Create the document
            $document = $service->create(
                $templateKey,
                $title,
                $data,
                $team->id,
                $context->user->id,
            );

            $result = [
                'id' => $document->id,
                'title' => $document->title,
                'template_key' => $document->template_key,
                'status' => $document->status,
                'share_url' => $document->share_url,
            ];

            // Auto-render: dispatch job for async PDF generation
            if ($autoRender) {
                RenderDocumentJob::dispatch($document->id, $context->user->id, $rendererOptions);
                $result['status'] = 'rendering';
                $result['hint'] = 'PDF wird asynchron gerendert. Die share_url kann sofort geteilt werden — sie zeigt den Status und Download-Button sobald das PDF fertig ist.';
            } else {
                $result['hint'] = 'Draft erstellt. Nutze core.documents.EXPORT um das PDF zu rendern.';
            }

            return ToolResult::success($result);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('Validierungsfehler: ' . $e->getMessage(), 'VALIDATION_ERROR');
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Erstellen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
