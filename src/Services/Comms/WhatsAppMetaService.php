<?php

namespace Platform\Core\Services\Comms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsWhatsAppMessage;
use Platform\Core\Models\CommsWhatsAppThread;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\User;
use Propaganistas\LaravelPhone\PhoneNumber;

class WhatsAppMetaService
{
    protected string $apiVersion = 'v21.0';

    /**
     * Send a text message via WhatsApp.
     */
    public function sendText(
        CommsChannel $channel,
        string $to,
        string $message,
        ?User $sender = null,
    ): CommsWhatsAppMessage {
        $this->validateChannel($channel);
        $creds = $this->getCredentials($channel);
        $phone = $this->normalizePhoneNumber($to);
        $convertedMessage = $this->convertHtmlToWhatsAppFormat($message);

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$creds['phone_number_id']}/messages";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$creds['access_token']}",
            'Content-Type' => 'application/json',
        ])->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $convertedMessage],
        ]);

        return $this->handleSendResponse($response, $channel, $phone, $message, 'text', $sender);
    }

    /**
     * Send a template message via WhatsApp.
     */
    public function sendTemplate(
        CommsChannel $channel,
        string $to,
        string $templateName,
        array $components = [],
        string $languageCode = 'en',
        ?User $sender = null,
    ): CommsWhatsAppMessage {
        $this->validateChannel($channel);
        $creds = $this->getCredentials($channel);
        $phone = $this->normalizePhoneNumber($to);

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$creds['phone_number_id']}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$creds['access_token']}",
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        return $this->handleSendResponse(
            $response,
            $channel,
            $phone,
            "Template: {$templateName}",
            'template',
            $sender,
            $templateName,
            $components
        );
    }

    /**
     * Send a media message via WhatsApp.
     */
    public function sendMedia(
        CommsChannel $channel,
        string $to,
        ContextFile $file,
        ?string $caption = null,
        ?User $sender = null,
    ): CommsWhatsAppMessage {
        $this->validateChannel($channel);
        $creds = $this->getCredentials($channel);
        $phone = $this->normalizePhoneNumber($to);

        $mediaType = $this->getMediaTypeFromMime($file->mime_type);
        if (!$mediaType) {
            throw new \InvalidArgumentException("Unsupported media type: {$file->mime_type}");
        }

        // Generate a temporary URL for the file
        $temporaryUrl = Storage::disk($file->disk)->temporaryUrl($file->path, now()->addMinutes(60));

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$creds['phone_number_id']}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => $mediaType,
            $mediaType => [
                'link' => $temporaryUrl,
            ],
        ];

        if (in_array($mediaType, ['image', 'video']) && $caption) {
            $payload[$mediaType]['caption'] = $caption;
        }

        if ($mediaType === 'document') {
            $payload[$mediaType]['filename'] = $file->original_name ?? $file->file_name;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$creds['access_token']}",
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        $message = $this->handleSendResponse($response, $channel, $phone, $caption ?? '', $mediaType, $sender);

        // Link the ContextFile to the message
        $message->addFileReference($file->id, ['role' => 'attachment']);

        return $message;
    }

    /**
     * Process an incoming WhatsApp message from the webhook.
     */
    public function processIncomingMessage(array $messageData, CommsChannel $channel): CommsWhatsAppMessage
    {
        $messageId = $messageData['id'] ?? null;
        $rawFrom = '+' . ($messageData['from'] ?? '');
        $phone = $this->normalizePhoneNumber($rawFrom);
        $timestamp = $messageData['timestamp'] ?? null;
        $messageType = $messageData['type'] ?? 'text';
        $text = $messageData['text']['body'] ?? '';

        // Normalize voice to audio for message_type storage
        // WhatsApp sends voice notes as type "audio" with voice=true, but some clients send as type "voice"
        $storedMessageType = $messageType;
        if ($messageType === 'voice') {
            $storedMessageType = 'audio';
        }

        // For media messages without text body, try to get caption
        if ($text === '' && in_array($messageType, ['image', 'video', 'audio', 'document', 'sticker', 'voice'])) {
            $text = $messageData[$messageType]['caption'] ?? '';
        }

        // Find or create thread
        $thread = CommsWhatsAppThread::findOrCreateForPhone($channel, $phone);

        // Check for duplicate
        if ($messageId && CommsWhatsAppMessage::query()->where('meta_message_id', $messageId)->exists()) {
            return CommsWhatsAppMessage::query()->where('meta_message_id', $messageId)->first();
        }

        // Create the message
        $message = $thread->messages()->create([
            'direction' => 'inbound',
            'meta_message_id' => $messageId,
            'body' => $text,
            'message_type' => $storedMessageType,
            'status' => 'received',
            'meta_payload' => $messageData,
            'sent_at' => $timestamp
                ? \Carbon\Carbon::createFromTimestampUTC($timestamp)->setTimezone(config('app.timezone'))
                : now(),
        ]);

        // Update thread rollups
        $preview = $text ?: match ($messageType) {
            'image' => '📷 Bild',
            'video' => '🎬 Video',
            'audio', 'voice' => '🎤 Sprachnachricht',
            'document' => '📄 Dokument',
            'sticker' => '🏷 Sticker',
            default => '',
        };
        $thread->update([
            'last_inbound_at' => $message->sent_at,
            'last_message_preview' => Str::limit($preview, 100),
            'is_unread' => true,
        ]);

        // Process media if present (now including sticker and voice)
        if (in_array($messageType, ['image', 'video', 'audio', 'document', 'sticker', 'voice'])) {
            try {
                $attachmentService = app(InboundWhatsAppAttachmentService::class);
                $attachmentService->processMediaAttachment($messageData, $message, $channel);
            } catch (\Throwable $e) {
                Log::error('WhatsApp media processing failed', [
                    'message_id' => $message->id,
                    'type' => $messageType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $message;
    }

    /**
     * Update message status from webhook status updates.
     */
    public function updateMessageStatus(array $statusData): void
    {
        $messageId = $statusData['id'] ?? null;
        $status = $statusData['status'] ?? null;
        $timestamp = $statusData['timestamp'] ?? null;

        if (!$messageId || !$status) {
            return;
        }

        $message = CommsWhatsAppMessage::query()
            ->where('meta_message_id', $messageId)
            ->first();

        if (!$message) {
            Log::debug("WhatsApp status update for unknown message: {$messageId}");
            return;
        }

        $updates = [
            'status' => $status,
            'status_updated_at' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp) : now(),
        ];

        if ($status === 'sent' && !$message->sent_at) {
            $updates['sent_at'] = $updates['status_updated_at'];
        }
        if ($status === 'delivered' && !$message->delivered_at) {
            $updates['delivered_at'] = $updates['status_updated_at'];
        }
        if ($status === 'read' && !$message->read_at) {
            $updates['read_at'] = $updates['status_updated_at'];
        }

        $message->update($updates);
    }

    /**
     * Handle the API response after sending a message.
     */
    protected function handleSendResponse(
        $response,
        CommsChannel $channel,
        string $phone,
        string $body,
        string $messageType,
        ?User $sender,
        ?string $templateName = null,
        ?array $templateParams = null,
    ): CommsWhatsAppMessage {
        $responseData = $response->json();

        $thread = CommsWhatsAppThread::findOrCreateForPhone($channel, $phone);

        $messageId = $responseData['messages'][0]['id'] ?? null;
        $status = $response->successful() ? 'sent' : 'failed';

        $message = $thread->messages()->create([
            'direction' => 'outbound',
            'meta_message_id' => $messageId,
            'body' => $body,
            'message_type' => $messageType,
            'template_name' => $templateName,
            'template_params' => $templateParams,
            'status' => $status,
            'status_updated_at' => now(),
            'sent_by_user_id' => $sender?->id,
            'sent_at' => $response->successful() ? now() : null,
            'meta_payload' => $responseData,
        ]);

        // Update thread rollups
        $thread->update([
            'last_outbound_at' => $message->sent_at ?? now(),
            'last_message_preview' => Str::limit($body, 100),
        ]);

        if (!$response->successful()) {
            Log::error('WhatsApp send failed', [
                'channel_id' => $channel->id,
                'phone' => $phone,
                'response' => $responseData,
            ]);
        }

        return $message;
    }

    /**
     * Get credentials from channel's meta or provider connection.
     */
    protected function getCredentials(CommsChannel $channel): array
    {
        // First try channel meta (where credentials are stored when channel is created)
        $meta = is_array($channel->meta) ? $channel->meta : [];
        $phoneNumberId = (string) ($meta['phone_number_id'] ?? '');
        $accessToken = (string) ($meta['access_token'] ?? '');

        // Fallback to provider connection credentials
        if ($phoneNumberId === '' || $accessToken === '') {
            $channel->loadMissing('providerConnection');
            $connection = $channel->providerConnection;
            $creds = is_array($connection?->credentials) ? $connection->credentials : [];

            $phoneNumberId = $phoneNumberId ?: (string) ($creds['phone_number_id'] ?? '');
            $accessToken = $accessToken ?: (string) ($creds['access_token'] ?? '');
        }

        if ($phoneNumberId === '' || $accessToken === '') {
            throw new \RuntimeException('Missing WhatsApp credentials in provider connection (phone_number_id, access_token).');
        }

        return [
            'phone_number_id' => $phoneNumberId,
            'access_token' => $accessToken,
            'integrations_whatsapp_account_id' => $meta['integrations_whatsapp_account_id'] ?? null,
        ];
    }

    /**
     * Normalize a phone number to E.164 format.
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        // Strip all non-numeric except leading +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // If no country code, try to parse
        try {
            $parsed = new PhoneNumber($cleaned);
            return $parsed->formatE164();
        } catch (\Exception $e) {
            // Return cleaned version without +
            return ltrim($cleaned, '+');
        }
    }

    /**
     * Convert HTML formatting to WhatsApp format.
     */
    protected function convertHtmlToWhatsAppFormat(string $html): string
    {
        $replacements = [
            '<b>' => '*', '</b>' => '*',
            '<strong>' => '*', '</strong>' => '*',
            '<i>' => '_', '</i>' => '_',
            '<em>' => '_', '</em>' => '_',
            '<strike>' => '~', '</strike>' => '~',
            '<s>' => '~', '</s>' => '~',
            '<u>' => '', '</u>' => '',
            '<br>' => "\n", '<br/>' => "\n", '<br />' => "\n",
            '<div>' => "\n", '</div>' => "\n",
            '&nbsp;' => ' ',
        ];

        return trim(strip_tags(strtr($html, $replacements)));
    }

    /**
     * Get WhatsApp media type from MIME type.
     */
    protected function getMediaTypeFromMime(string $mimeType): ?string
    {
        // Clean MIME type (remove codec info like "; codecs=opus")
        $cleanMime = trim(strtok($mimeType, ';'));

        $typeMap = [
            'image/jpeg' => 'image',
            'image/png' => 'image',
            'image/webp' => 'image',
            'image/gif' => 'image',
            'video/mp4' => 'video',
            'video/3gp' => 'video',
            'application/pdf' => 'document',
            'application/msword' => 'document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
            'application/vnd.ms-excel' => 'document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'document',
            'application/vnd.ms-powerpoint' => 'document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'document',
            'text/plain' => 'document',
            'application/zip' => 'document',
            'audio/aac' => 'audio',
            'audio/amr' => 'audio',
            'audio/mpeg' => 'audio',
            'audio/mp4' => 'audio',
            'audio/ogg' => 'audio',
        ];

        return $typeMap[$cleanMime] ?? null;
    }

    /**
     * Determine file extension from MIME type.
     */
    protected function determineExtension(string $mimeType): string
    {
        $mimeMap = [
            'audio/aac' => 'aac',
            'audio/amr' => 'amr',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/ogg' => 'ogg',
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
            'video/3gp' => '3gp',
            'video/mp4' => 'mp4',
        ];

        return $mimeMap[$mimeType] ?? 'bin';
    }

    /**
     * Validate that the channel is a WhatsApp channel.
     */
    protected function validateChannel(CommsChannel $channel): void
    {
        if ($channel->type !== 'whatsapp' || $channel->provider !== 'whatsapp_meta') {
            throw new \InvalidArgumentException('Channel must be type=whatsapp and provider=whatsapp_meta.');
        }
    }
}
