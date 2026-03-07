<?php

namespace Platform\Core\Services\Documents;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Platform\Core\Contracts\DocumentRendererContract;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\Document;
use Platform\Core\Models\DocumentExport;
use Platform\Core\Models\DocumentTemplate;

class DocumentService
{
    public function __construct(
        protected DocumentTemplateRegistry $templateRegistry,
        protected DocumentRendererContract $renderer,
    ) {}

    /**
     * Create a draft document from a template.
     */
    public function create(
        string $templateKey,
        string $title,
        array $data,
        int $teamId,
        ?int $userId = null,
        array $meta = [],
        ?int $folderId = null,
    ): Document {
        $template = $this->templateRegistry->resolve($templateKey, $teamId);

        if (!$template) {
            throw new \RuntimeException("Template '{$templateKey}' not found.");
        }

        // Validate data against schema if defined
        if ($template->schema) {
            $this->validateData($data, $template->schema);
        }

        return Document::create([
            'team_id' => $teamId,
            'document_folder_id' => $folderId,
            'document_template_id' => $template->exists ? $template->id : null,
            'template_key' => $templateKey,
            'title' => $title,
            'data' => $data,
            'status' => 'draft',
            'meta' => $meta ?: null,
            'created_by_user_id' => $userId,
            'share_token' => Str::random(48),
        ]);
    }

    /**
     * Render an existing document and store the output as ContextFile.
     */
    public function renderAndStore(
        Document $document,
        ?int $userId = null,
        array $rendererOptions = [],
    ): DocumentExport {
        $template = $document->template
            ?? $this->templateRegistry->resolve($document->template_key, $document->team_id);

        if (!$template) {
            $document->update(['status' => 'failed']);
            throw new \RuntimeException("Template '{$document->template_key}' not found for rendering.");
        }

        // Create export record
        $export = DocumentExport::create([
            'document_id' => $document->id,
            'exported_by_user_id' => $userId,
            'renderer' => $this->renderer->getRendererKey(),
            'status' => 'processing',
            'renderer_options' => $rendererOptions ?: null,
        ]);

        try {
            // Merge paper config from template into renderer options
            $options = array_merge($template->paper_config, $rendererOptions);

            // Add header/footer from template meta
            $templateMeta = $template->meta ?? [];
            if (!empty($templateMeta['header_html']) && empty($options['header_html'])) {
                $options['header_html'] = $templateMeta['header_html'];
            }
            if (!empty($templateMeta['footer_html']) && empty($options['footer_html'])) {
                $options['footer_html'] = $templateMeta['footer_html'];
            }

            // Render HTML
            $html = $this->templateRegistry->renderToHtml($template, $document->data ?? []);

            // Render to binary
            $binary = $this->renderer->render($html, $options);

            // Store as ContextFile
            $contextFile = $this->storePdfAsContextFile(
                $binary,
                $document,
                $userId,
            );

            // Update document
            $document->update([
                'status' => 'rendered',
                'output_context_file_id' => $contextFile->id,
            ]);

            // Update export
            $export->update([
                'status' => 'complete',
                'output_context_file_id' => $contextFile->id,
            ]);

            return $export->fresh();
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $document->update(['status' => 'failed']);

            throw $e;
        }
    }

    /**
     * Convenience: create + render in one call.
     */
    public function createAndRender(
        string $templateKey,
        string $title,
        array $data,
        int $teamId,
        ?int $userId = null,
        array $meta = [],
        array $rendererOptions = [],
    ): Document {
        $document = $this->create($templateKey, $title, $data, $teamId, $userId, $meta);

        $this->renderAndStore($document, $userId, $rendererOptions);

        return $document->fresh();
    }

    /**
     * Store rendered PDF as a ContextFile (flat storage, 32-char token).
     */
    protected function storePdfAsContextFile(
        string $binary,
        Document $document,
        ?int $userId,
    ): ContextFile {
        $disk = config('filesystems.default', 'public');
        $token = Str::random(32);
        $extension = $this->renderer->getOutputExtension();
        $mimeType = $this->renderer->getOutputMimeType();
        $fileName = "{$token}.{$extension}";

        Storage::disk($disk)->put($fileName, $binary);

        return ContextFile::create([
            'token' => $token,
            'team_id' => $document->team_id,
            'user_id' => $userId,
            'context_type' => Document::class,
            'context_id' => $document->id,
            'disk' => $disk,
            'path' => $fileName,
            'file_name' => $fileName,
            'original_name' => Str::slug($document->title) . ".{$extension}",
            'mime_type' => $mimeType,
            'file_size' => strlen($binary),
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'renderer' => $this->renderer->getRendererKey(),
                'template_key' => $document->template_key,
            ],
            'variants_status' => 'none',
        ]);
    }

    /**
     * Basic data validation against a JSON Schema definition.
     */
    protected function validateData(array $data, array $schema): void
    {
        // Validate required fields
        $required = $schema['required'] ?? [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing from template data.");
            }
        }

        // Validate field types if properties are defined
        $properties = $schema['properties'] ?? [];
        foreach ($properties as $field => $definition) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $expectedType = $definition['type'] ?? null;
            if ($expectedType && !$this->matchesType($data[$field], $expectedType)) {
                throw new \InvalidArgumentException("Field '{$field}' must be of type '{$expectedType}'.");
            }
        }
    }

    protected function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer', 'number' => is_numeric($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) || is_object($value),
            default => true,
        };
    }
}
