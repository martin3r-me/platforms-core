<?php

namespace Platform\Core\Services\Comms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsWhatsAppMessage;
use Platform\Core\Models\CommsWhatsAppThread;
use Platform\Core\Services\ContextFileService;

/**
 * Service für die Verarbeitung von Inbound-WhatsApp-Medien als ContextFiles.
 *
 * Wandelt WhatsApp Media (Bilder, Videos, Audio, Dokumente, Sticker, Voice) in ContextFiles um und hängt sie an:
 * 1. Die konkrete WhatsApp-Message (via ContextFileReference) – NICHT an den Thread
 * 2. Das Kontext-Objekt des Threads, falls vorhanden (Ticket, Aufgabe etc.) – via ContextFileReference
 *
 * Analog zu InboundMailAttachmentService, aber für WhatsApp Business API Media.
 */
class InboundWhatsAppAttachmentService
{
    /** Unterstützte WhatsApp Media-Types */
    private const SUPPORTED_MEDIA_TYPES = [
        'image',
        'video',
        'audio',
        'document',
        'sticker',
        'voice',
    ];

    /** Max attachment size for ContextFile processing (16 MB – WhatsApp limit) */
    private const MAX_ATTACHMENT_SIZE = 16 * 1024 * 1024;

    /** WhatsApp Graph API version */
    private const API_VERSION = 'v21.0';

    /** HTTP timeout for media download (seconds) */
    private const DOWNLOAD_TIMEOUT = 30;

    /** HTTP timeout for media URL fetch (seconds) */
    private const FETCH_URL_TIMEOUT = 10;

    /** MIME type mapping for WhatsApp media types without explicit MIME */
    private const FALLBACK_MIME_TYPES = [
        'image' => 'image/jpeg',
        'video' => 'video/mp4',
        'audio' => 'audio/ogg',
        'document' => 'application/octet-stream',
        'sticker' => 'image/webp',
        'voice' => 'audio/ogg',
    ];

    /** Extension mapping for common MIME types */
    private const MIME_EXTENSION_MAP = [
        'audio/aac' => 'aac',
        'audio/amr' => 'amr',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/ogg' => 'ogg',
        'audio/ogg; codecs=opus' => 'ogg',
        'text/plain' => 'txt',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/3gp' => '3gp',
        'video/mp4' => 'mp4',
    ];

    /**
     * Verarbeitet ein Media-Attachment einer eingehenden WhatsApp-Nachricht.
     *
     * 1. Lädt das Medium über die WhatsApp Business API herunter
     * 2. Speichert es als ContextFile
     * 3. Hängt es an die Message (via ContextFileReference)
     * 4. Hängt es an das Kontext-Objekt des Threads, falls vorhanden
     */
    public function processMediaAttachment(
        array $messageData,
        CommsWhatsAppMessage $message,
        CommsChannel $channel,
    ): void {
        $type = $messageData['type'] ?? null;

        if (!$type || !in_array($type, self::SUPPORTED_MEDIA_TYPES, true)) {
            return;
        }

        // Voice messages come as type "audio" but with voice flag, or as separate type
        // WhatsApp API sends voice notes under 'audio' with voice=true
        $isVoice = ($type === 'audio' && !empty($messageData['audio']['voice']));
        $effectiveType = $isVoice ? 'voice' : $type;

        $mediaBlock = $messageData[$type] ?? null;
        if (!is_array($mediaBlock) || empty($mediaBlock['id'])) {
            Log::warning('[InboundWhatsAppAttachment] Kein gültiger Media-Block', [
                'message_id' => $message->id,
                'type' => $type,
            ]);
            return;
        }

        try {
            $this->processMedia(
                $mediaBlock,
                $effectiveType,
                $message,
                $channel,
            );
        } catch (\Throwable $e) {
            Log::error('[InboundWhatsAppAttachment] Fehler bei Media-Verarbeitung', [
                'message_id' => $message->id,
                'media_id' => $mediaBlock['id'] ?? null,
                'type' => $effectiveType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Verarbeitet ein einzelnes Medium.
     */
    private function processMedia(
        array $mediaBlock,
        string $mediaType,
        CommsWhatsAppMessage $message,
        CommsChannel $channel,
    ): void {
        $mediaId = (string) $mediaBlock['id'];
        $mimeType = (string) ($mediaBlock['mime_type'] ?? self::FALLBACK_MIME_TYPES[$mediaType] ?? 'application/octet-stream');
        $originalFileName = $mediaBlock['filename'] ?? null;
        $caption = $mediaBlock['caption'] ?? null;
        $fileSize = $mediaBlock['file_size'] ?? null;

        // Clean up MIME type (remove codec info for extension lookup)
        $cleanMimeType = strtok($mimeType, ';');
        $cleanMimeType = trim($cleanMimeType);

        // Size check (if file_size is provided by API)
        if ($fileSize !== null && (int) $fileSize > self::MAX_ATTACHMENT_SIZE) {
            Log::warning('[InboundWhatsAppAttachment] Medium zu groß, übersprungen', [
                'media_id' => $mediaId,
                'type' => $mediaType,
                'size' => $fileSize,
                'max' => self::MAX_ATTACHMENT_SIZE,
            ]);
            return;
        }

        // Get channel credentials
        $creds = $this->getCredentials($channel);

        // Step 1: Fetch download URL from Meta API
        $mediaUrl = $this->fetchMediaUrl($mediaId, $creds['access_token']);
        if (!$mediaUrl) {
            Log::error('[InboundWhatsAppAttachment] Media-URL konnte nicht abgerufen werden', [
                'media_id' => $mediaId,
            ]);
            return;
        }

        // Step 2: Download media content
        $content = $this->downloadMedia($mediaUrl, $creds['access_token']);
        if ($content === null) {
            Log::error('[InboundWhatsAppAttachment] Media-Download fehlgeschlagen', [
                'media_id' => $mediaId,
            ]);
            return;
        }

        // Post-download size check
        $actualSize = strlen($content);
        if ($actualSize === 0) {
            Log::warning('[InboundWhatsAppAttachment] Leere Datei, übersprungen', [
                'media_id' => $mediaId,
            ]);
            return;
        }

        if ($actualSize > self::MAX_ATTACHMENT_SIZE) {
            Log::warning('[InboundWhatsAppAttachment] Heruntergeladenes Medium zu groß', [
                'media_id' => $mediaId,
                'size' => $actualSize,
                'max' => self::MAX_ATTACHMENT_SIZE,
            ]);
            return;
        }

        // Step 3: Generate filename
        $extension = self::MIME_EXTENSION_MAP[$cleanMimeType] ?? self::MIME_EXTENSION_MAP[$mimeType] ?? 'bin';
        if (!$originalFileName) {
            $prefix = match ($mediaType) {
                'image' => 'IMG',
                'video' => 'VID',
                'audio', 'voice' => 'AUD',
                'sticker' => 'STK',
                'document' => 'DOC',
                default => 'FILE',
            };
            $originalFileName = "{$prefix}_" . date('Ymd_His') . '_' . Str::random(6) . ".{$extension}";
        }

        // Step 4: Create ContextFile via ContextFileService
        $thread = $message->thread;
        $contextFileService = app(ContextFileService::class);

        $tempPath = tempnam(sys_get_temp_dir(), 'wa_media_');
        if ($tempPath === false) {
            Log::error('[InboundWhatsAppAttachment] Temp-Datei konnte nicht erstellt werden');
            return;
        }

        try {
            file_put_contents($tempPath, $content);

            $uploadedFile = new UploadedFile(
                $tempPath,
                $originalFileName,
                $cleanMimeType,
                null,
                true // test mode – skip is_uploaded_file check
            );

            $isImage = str_starts_with($cleanMimeType, 'image/');

            // ContextFile über den Standard-Service erstellen
            // context_type = WhatsAppMessage-Klasse, context_id = Message-ID
            $result = $contextFileService->uploadForContext(
                $uploadedFile,
                CommsWhatsAppMessage::class,
                $message->id,
                [
                    'team_id' => $thread->team_id,
                    'user_id' => null, // Inbound-Nachricht hat keinen User – system upload
                    'generate_variants' => $isImage,
                ]
            );

            $contextFileId = $result['id'];

            // Step 5: ContextFile an die WhatsApp-Message hängen (NICHT an den Thread!)
            $message->addFileReference($contextFileId, [
                'title' => $originalFileName,
                'caption' => $caption,
                'source' => 'inbound_whatsapp',
                'media_type' => $mediaType,
                'original_mime' => $mimeType,
                'media_id' => $mediaId,
            ]);

            // Step 6: Caption als Message-Body setzen, falls noch nicht vorhanden
            if ($caption && !$message->body) {
                $message->update(['body' => $caption]);
            }

            // Step 7: ContextFile zusätzlich an Kontext-Objekt hängen, falls Thread eines hat
            $this->attachToContextObject($thread, $contextFileId, $originalFileName, $mimeType, $message, $mediaType);

            Log::info('[InboundWhatsAppAttachment] Medium verarbeitet', [
                'message_id' => $message->id,
                'context_file_id' => $contextFileId,
                'type' => $mediaType,
                'filename' => $originalFileName,
                'mime' => $cleanMimeType,
                'size' => $actualSize,
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Hängt ein ContextFile an das Kontext-Objekt des Threads (falls vorhanden).
     *
     * Loose Kopplung: Prüft über polymorphe Felder context_model / context_model_id
     * und method_exists statt direkter Modul-Imports.
     */
    private function attachToContextObject(
        CommsWhatsAppThread $thread,
        int $contextFileId,
        string $fileName,
        string $mimeType,
        CommsWhatsAppMessage $message,
        string $mediaType,
    ): void {
        $contextModel = $thread->context_model;
        $contextModelId = $thread->context_model_id;

        if (!$contextModel || !$contextModelId) {
            return;
        }

        try {
            if (!class_exists($contextModel)) {
                return;
            }

            $contextObject = $contextModel::find($contextModelId);
            if (!$contextObject) {
                return;
            }

            // Prüfen ob das Kontext-Objekt die HasContextFileReferences-Trait nutzt
            if (!method_exists($contextObject, 'addFileReference')) {
                Log::info('[InboundWhatsAppAttachment] Kontext-Objekt unterstützt keine ContextFileReferences', [
                    'context_model' => $contextModel,
                    'context_model_id' => $contextModelId,
                ]);
                return;
            }

            $contextObject->addFileReference($contextFileId, [
                'title' => $fileName,
                'source' => 'inbound_whatsapp',
                'media_type' => $mediaType,
                'whatsapp_message_id' => $message->id,
                'thread_id' => $thread->id,
                'original_mime' => $mimeType,
            ]);

            Log::info('[InboundWhatsAppAttachment] ContextFile an Kontext-Objekt gehängt', [
                'context_file_id' => $contextFileId,
                'context_model' => $contextModel,
                'context_model_id' => $contextModelId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[InboundWhatsAppAttachment] ContextFile konnte nicht an Kontext-Objekt gehängt werden', [
                'context_file_id' => $contextFileId,
                'context_model' => $contextModel,
                'context_model_id' => $contextModelId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch the download URL for a media file from Meta Graph API.
     */
    private function fetchMediaUrl(string $mediaId, string $accessToken): ?string
    {
        $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$mediaId}";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->timeout(self::FETCH_URL_TIMEOUT)->get($url);

            if (!$response->successful()) {
                Log::error('[InboundWhatsAppAttachment] Media-URL Abruf fehlgeschlagen', [
                    'media_id' => $mediaId,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);
                return null;
            }

            return $response->json('url');
        } catch (\Exception $e) {
            Log::error('[InboundWhatsAppAttachment] Exception bei Media-URL Abruf', [
                'media_id' => $mediaId,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Download media content from Meta CDN.
     */
    private function downloadMedia(string $mediaUrl, string $accessToken): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->timeout(self::DOWNLOAD_TIMEOUT)->get($mediaUrl);

            if (!$response->successful()) {
                Log::error('[InboundWhatsAppAttachment] Media-Download fehlgeschlagen', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->body();
        } catch (\Exception $e) {
            Log::error('[InboundWhatsAppAttachment] Exception bei Media-Download', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get credentials from channel's meta or provider connection.
     */
    private function getCredentials(CommsChannel $channel): array
    {
        $meta = is_array($channel->meta) ? $channel->meta : [];
        $phoneNumberId = (string) ($meta['phone_number_id'] ?? '');
        $accessToken = (string) ($meta['access_token'] ?? '');

        if ($phoneNumberId === '' || $accessToken === '') {
            $channel->loadMissing('providerConnection');
            $connection = $channel->providerConnection;
            $creds = is_array($connection?->credentials) ? $connection->credentials : [];

            $phoneNumberId = $phoneNumberId ?: (string) ($creds['phone_number_id'] ?? '');
            $accessToken = $accessToken ?: (string) ($creds['access_token'] ?? '');
        }

        if ($accessToken === '') {
            throw new \RuntimeException('Missing WhatsApp access_token for media download.');
        }

        return [
            'phone_number_id' => $phoneNumberId,
            'access_token' => $accessToken,
        ];
    }
}
