<?php

namespace Platform\Core\Services\Comms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\CommsEmailInboundMail;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Services\ContextFileService;

/**
 * Service für die Verarbeitung von Inbound-Mail-Anhängen als ContextFiles.
 *
 * Wandelt Mail-Attachments in ContextFiles um und hängt sie an:
 * 1. Die konkrete InboundMail-Nachricht (via ContextFileReference)
 * 2. Das Kontext-Objekt des Threads, falls vorhanden (via ContextFileReference)
 */
class InboundMailAttachmentService
{
    /** Max attachment size for ContextFile processing (10 MB) */
    private const MAX_ATTACHMENT_SIZE = 10 * 1024 * 1024;

    /** MIME types that should be skipped (potentially dangerous) */
    private const BLOCKED_MIME_TYPES = [
        'application/x-msdownload',
        'application/x-executable',
        'application/x-msdos-program',
        'application/x-bat',
        'application/x-cmd',
        'application/x-msi',
        'application/x-vbs',
        'application/x-powershell',
    ];

    /**
     * Verarbeitet alle Anhänge einer Inbound-Mail als ContextFiles.
     *
     * @param CommsEmailInboundMail $mail    Die eingehende Mail
     * @param CommsEmailThread      $thread  Der zugehörige Thread
     * @param array                 $payload Rohes Postmark-Payload (Attachments-Array)
     */
    public function processAttachments(
        CommsEmailInboundMail $mail,
        CommsEmailThread $thread,
        array $attachmentsPayload
    ): void {
        if (empty($attachmentsPayload)) {
            return;
        }

        $contextFileService = app(ContextFileService::class);

        // Kontext-Objekt des Threads prüfen (loose gekoppelt über polymorphe Felder)
        $contextModel = $thread->context_model;
        $contextModelId = $thread->context_model_id;
        $hasContext = $contextModel && $contextModelId;

        // Kontext-Objekt auflösen, falls vorhanden und HasContextFileReferences unterstützt
        $contextObject = null;
        if ($hasContext) {
            try {
                if (class_exists($contextModel)) {
                    $contextObject = $contextModel::find($contextModelId);
                    // Prüfen ob das Kontext-Objekt die HasContextFileReferences-Trait nutzt
                    if ($contextObject && !method_exists($contextObject, 'addFileReference')) {
                        Log::info('[InboundMailAttachment] Kontext-Objekt unterstützt keine ContextFileReferences', [
                            'context_model' => $contextModel,
                            'context_model_id' => $contextModelId,
                        ]);
                        $contextObject = null;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[InboundMailAttachment] Kontext-Objekt konnte nicht aufgelöst werden', [
                    'context_model' => $contextModel,
                    'context_model_id' => $contextModelId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($attachmentsPayload as $attachment) {
            try {
                $this->processAttachment(
                    $attachment,
                    $mail,
                    $thread,
                    $contextFileService,
                    $contextObject
                );
            } catch (\Throwable $e) {
                Log::error('[InboundMailAttachment] Fehler bei Anhang-Verarbeitung', [
                    'mail_id' => $mail->id,
                    'thread_id' => $thread->id,
                    'filename' => $attachment['Name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Weiter mit nächstem Anhang – ein fehlgeschlagener Anhang soll
                // nicht die gesamte Verarbeitung stoppen
            }
        }
    }

    /**
     * Verarbeitet einen einzelnen Anhang.
     */
    private function processAttachment(
        array $attachment,
        CommsEmailInboundMail $mail,
        CommsEmailThread $thread,
        ContextFileService $contextFileService,
        ?object $contextObject
    ): void {
        $name = (string) ($attachment['Name'] ?? 'attachment');
        $content = (string) ($attachment['Content'] ?? '');
        $mimeType = (string) ($attachment['ContentType'] ?? 'application/octet-stream');
        $contentLength = (int) ($attachment['ContentLength'] ?? 0);
        $contentId = $attachment['ContentID'] ?? null;
        $isInline = !empty($contentId);

        // Skip: Kein Inhalt
        if ($content === '') {
            Log::debug('[InboundMailAttachment] Anhang übersprungen: kein Inhalt', ['filename' => $name]);
            return;
        }

        // Skip: Inline-Bilder (eingebettete CID-Referenzen im HTML-Body) – werden nicht als ContextFile behandelt
        if ($isInline) {
            Log::debug('[InboundMailAttachment] Inline-Anhang übersprungen', [
                'filename' => $name,
                'cid' => $contentId,
            ]);
            return;
        }

        // Skip: Blockierte MIME-Types
        if (in_array(strtolower($mimeType), self::BLOCKED_MIME_TYPES, true)) {
            Log::warning('[InboundMailAttachment] Anhang mit blockiertem MIME-Type übersprungen', [
                'filename' => $name,
                'mime' => $mimeType,
            ]);
            return;
        }

        // Dekodieren
        $decodedContent = base64_decode($content, true);
        if ($decodedContent === false) {
            Log::warning('[InboundMailAttachment] Base64-Dekodierung fehlgeschlagen', ['filename' => $name]);
            return;
        }

        // Size check
        $fileSize = strlen($decodedContent);
        if ($fileSize > self::MAX_ATTACHMENT_SIZE) {
            Log::warning('[InboundMailAttachment] Anhang zu groß, übersprungen', [
                'filename' => $name,
                'size' => $fileSize,
                'max' => self::MAX_ATTACHMENT_SIZE,
            ]);
            return;
        }

        // Leere Dateien überspringen
        if ($fileSize === 0) {
            Log::debug('[InboundMailAttachment] Leerer Anhang übersprungen', ['filename' => $name]);
            return;
        }

        // Temporäre Datei erstellen für ContextFileService
        $tempPath = tempnam(sys_get_temp_dir(), 'inbound_mail_');
        if ($tempPath === false) {
            Log::error('[InboundMailAttachment] Temp-Datei konnte nicht erstellt werden');
            return;
        }

        try {
            file_put_contents($tempPath, $decodedContent);

            $uploadedFile = new UploadedFile(
                $tempPath,
                $name,
                $mimeType,
                null,
                true // test mode – skip is_uploaded_file check
            );

            // ContextFile über den Standard-Service erstellen
            // context_type = InboundMail-Klasse, context_id = Mail-ID
            $result = $contextFileService->uploadForContext(
                $uploadedFile,
                CommsEmailInboundMail::class,
                $mail->id,
                [
                    'team_id' => $thread->team_id,
                    'user_id' => null, // Inbound-Mail hat keinen User – system upload
                    'generate_variants' => str_starts_with($mimeType, 'image/'),
                ]
            );

            $contextFileId = $result['id'];

            // 1) ContextFile an die InboundMail-Nachricht hängen (via ContextFileReference)
            $mail->addFileReference($contextFileId, [
                'title' => $name,
                'source' => 'inbound_mail',
                'original_mime' => $mimeType,
            ]);

            // 2) ContextFile zusätzlich an das Kontext-Objekt hängen, falls vorhanden
            if ($contextObject) {
                try {
                    $contextObject->addFileReference($contextFileId, [
                        'title' => $name,
                        'source' => 'inbound_mail',
                        'inbound_mail_id' => $mail->id,
                        'thread_id' => $thread->id,
                        'original_mime' => $mimeType,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[InboundMailAttachment] ContextFile konnte nicht an Kontext-Objekt gehängt werden', [
                        'context_file_id' => $contextFileId,
                        'context_model' => $thread->context_model,
                        'context_model_id' => $thread->context_model_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('[InboundMailAttachment] Anhang verarbeitet', [
                'mail_id' => $mail->id,
                'context_file_id' => $contextFileId,
                'filename' => $name,
                'mime' => $mimeType,
                'size' => $fileSize,
                'attached_to_context' => $contextObject !== null,
            ]);
        } finally {
            @unlink($tempPath);
        }
    }
}
