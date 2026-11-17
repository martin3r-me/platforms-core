<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\DirectUploadService;

/**
 * Direkt-Upload Schritt 1: liefert einen signierten Einmal-Link (nur per MCP ausgegeben).
 */
class RequestContextFileUploadTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.context.files.upload.request';
    }

    public function getDescription(): string
    {
        return 'Fordert einen signierten, kurzlebigen Einmal-Upload-Link an (Direkt-Upload bis 100MB, Inhalt geht ohne Zwischenablage nach S3). '
            . 'Danach die Datei per HTTP PUT (Body = Rohbytes, Content-Length = total_size) an upload_url senden, '
            . 'dann core.context.files.upload.finish mit upload_id aufrufen. context_type/context_id sind optional; '
            . 'ohne Kontext kann die Datei später mit core.context.files.attach verknüpft werden (unverknüpfte Dateien werden nach 24h gelöscht).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'context_type' => ['type' => 'string', 'description' => 'Optional: vollqualifizierter Model-Klassenname'],
                'context_id' => ['type' => 'integer', 'description' => 'Optional: ID des Context-Objekts (zusammen mit context_type)'],
                'file_name' => ['type' => 'string', 'description' => 'Dateiname inkl. Endung'],
                'mime_type' => ['type' => 'string', 'description' => 'Optional: MIME-Typ'],
                'total_size' => ['type' => 'integer', 'description' => 'Dateigröße in Bytes (max. 100MB)'],
                'sha256' => ['type' => 'string', 'description' => 'Optional: SHA-256 (hex), wird bei upload.finish geprüft'],
            ],
            'required' => ['file_name', 'total_size'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }
            $fileName = trim((string) ($arguments['file_name'] ?? ''));
            $size = (int) ($arguments['total_size'] ?? 0);
            $type = $arguments['context_type'] ?? null;
            $id = (int) ($arguments['context_id'] ?? 0);
            $sha = isset($arguments['sha256']) ? strtolower((string) $arguments['sha256']) : null;

            if ($fileName === '' || $size <= 0) {
                return ToolResult::error('file_name und total_size (>0) sind erforderlich', 'VALIDATION_ERROR');
            }
            if ($size > DirectUploadService::MAX_SIZE) {
                return ToolResult::error('Datei ist zu groß (max. 100MB)', 'FILE_TOO_LARGE');
            }
            if ($sha !== null && !preg_match('/^[a-f0-9]{64}$/', $sha)) {
                return ToolResult::error('sha256 muss ein 64-stelliger Hex-String sein', 'VALIDATION_ERROR');
            }
            if (($type && !$id) || (!$type && $id)) {
                return ToolResult::error('context_type und context_id nur gemeinsam angeben', 'VALIDATION_ERROR');
            }

            $service = app(DirectUploadService::class);
            if ($type) {
                if ($err = $service->validateContext($type, $id, (int) $context->team->id)) {
                    return ToolResult::error($err, 'INVALID_CONTEXT');
                }
            }

            [$upload, $url] = $service->request([
                'team_id' => $context->team->id,
                'user_id' => $context->user->id,
                'context_type' => $type ?: null,
                'context_id' => $type ? $id : null,
                'file_name' => $fileName,
                'mime_type' => $arguments['mime_type'] ?? null,
                'total_size' => $size,
                'sha256' => $sha,
            ]);

            return ToolResult::success([
                'upload_id' => $upload->uuid,
                'upload_url' => $url,
                'method' => 'PUT',
                'expires_at' => $upload->expires_at->toIso8601String(),
                'hint' => 'z.B. curl -X PUT --data-binary @datei "<upload_url>" — Link ist einmalig. Danach core.context.files.upload.finish aufrufen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler bei upload.request: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
