<?php

namespace Platform\Core\Http\Controllers\Comms;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Platform\Core\Events\CommsWhatsAppInboundReceived;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Services\Comms\WhatsAppMetaService;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppMetaService $whatsAppService,
    ) {}

    /**
     * Handle both GET (verification) and POST (webhook) requests.
     */
    public function handle(Request $request): Response
    {
        if ($request->isMethod('get')) {
            return $this->verify($request);
        }

        return $this->processWebhook($request);
    }

    /**
     * Meta Webhook Verification (GET).
     *
     * Meta sends a GET request with hub.mode, hub.verify_token, and hub.challenge
     * to verify ownership of the webhook URL.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $verifyToken = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Load verify token from config or environment
        $expectedToken = config('services.whatsapp.verify_token', env('WHATSAPP_VERIFY_TOKEN'));

        if ($mode === 'subscribe' && $verifyToken === $expectedToken) {
            Log::info('WhatsApp webhook verification successful');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'verify_token_received' => $verifyToken,
        ]);

        return response('Verification failed', 403);
    }

    /**
     * Process incoming webhook payload (POST).
     */
    protected function processWebhook(Request $request): Response
    {
        try {
            // Validate signature
            if (!$this->validateSignature($request)) {
                Log::warning('WhatsApp webhook signature validation failed');
                return response('Invalid signature', 401);
            }

            $payload = $request->json()->all();

            // Parse the webhook structure
            foreach ($payload['entry'] ?? [] as $entry) {
                $this->processEntry($entry);
            }

            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent Meta from retrying
            return response('OK', 200);
        }
    }

    /**
     * Process a single entry from the webhook payload.
     */
    protected function processEntry(array $entry): void
    {
        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];

            // Get the phone_number_id from the metadata
            $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
            if (!$phoneNumberId) {
                continue;
            }

            // Find the channel by phone_number_id in provider connection credentials
            $channel = $this->findChannelByPhoneNumberId($phoneNumberId);
            if (!$channel) {
                Log::warning('WhatsApp webhook: No channel found for phone_number_id', [
                    'phone_number_id' => $phoneNumberId,
                ]);
                continue;
            }

            // Process messages
            foreach ($value['messages'] ?? [] as $messageData) {
                $this->processMessage($messageData, $channel);
            }

            // Process status updates
            foreach ($value['statuses'] ?? [] as $statusData) {
                $this->processStatus($statusData);
            }
        }
    }

    /**
     * Process an incoming message.
     */
    protected function processMessage(array $messageData, CommsChannel $channel): void
    {
        $messageId = $messageData['id'] ?? '[unknown]';
        $from = $messageData['from'] ?? '[unknown]';

        Log::info("WhatsApp incoming message: {$messageId} from {$from}");

        try {
            $message = $this->whatsAppService->processIncomingMessage($messageData, $channel);

            Log::info("WhatsApp message processed: #{$message->id}");

            // Dispatch event for module listeners (e.g., Helpdesk, HCM)
            event(new CommsWhatsAppInboundReceived(
                $channel,
                $message->thread,
                $message,
                $message->thread->wasRecentlyCreated
            ));
        } catch (\Throwable $e) {
            Log::error("WhatsApp message processing error: {$e->getMessage()}", [
                'message_id' => $messageId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process a status update.
     */
    protected function processStatus(array $statusData): void
    {
        $messageId = $statusData['id'] ?? '[unknown]';
        $status = $statusData['status'] ?? '[unknown]';

        Log::debug("WhatsApp status update: {$messageId} -> {$status}");

        try {
            $this->whatsAppService->updateMessageStatus($statusData);
        } catch (\Throwable $e) {
            Log::error("WhatsApp status update error: {$e->getMessage()}", [
                'message_id' => $messageId,
            ]);
        }
    }

    /**
     * Find a CommsChannel by the WhatsApp phone_number_id.
     */
    protected function findChannelByPhoneNumberId(string $phoneNumberId): ?CommsChannel
    {
        // First try to find in channel meta (where credentials are stored when channel is created)
        $channel = CommsChannel::query()
            ->where('type', 'whatsapp')
            ->where('provider', 'whatsapp_meta')
            ->where('is_active', true)
            ->whereJsonContains('meta->phone_number_id', $phoneNumberId)
            ->first();

        if ($channel) {
            return $channel;
        }

        // Fallback: search in provider connection credentials
        return CommsChannel::query()
            ->where('type', 'whatsapp')
            ->where('provider', 'whatsapp_meta')
            ->where('is_active', true)
            ->whereHas('providerConnection', function ($query) use ($phoneNumberId) {
                $query->whereJsonContains('credentials->phone_number_id', $phoneNumberId);
            })
            ->with('providerConnection')
            ->first();
    }

    /**
     * Validate the X-Hub-Signature-256 header.
     */
    protected function validateSignature(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret', env('WHATSAPP_APP_SECRET'));

        // Skip validation if no secret is configured
        if (empty($appSecret)) {
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (empty($signature)) {
            return false;
        }

        $rawPayload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
