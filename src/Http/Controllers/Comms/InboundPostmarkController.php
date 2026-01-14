<?php

namespace Platform\Core\Http\Controllers\Comms;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailInboundMail;
use Platform\Core\Models\CommsEmailThread;
use Platform\Comms\Services\CommsActivityService;

class InboundPostmarkController extends Controller
{
    public function __invoke(Request $request)
    {
        $raw = (string) $request->getContent();
        $payload = $request->json()->all();

        try {
            // 0) Duplicate protection via Postmark MessageID
            $postmarkId = $payload['MessageID'] ?? null;
            if ($postmarkId && CommsEmailInboundMail::query()->where('postmark_id', $postmarkId)->exists()) {
                return response()->noContent();
            }

            // 1) Resolve channel by recipient
            $channel = $this->findChannelByRecipients($payload);
            if (!$channel) {
                Log::warning('Postmark inbound rejected: unknown recipient', [
                    'to' => $payload['To'] ?? null,
                    'to_full' => $payload['ToFull'] ?? null,
                    'from' => $payload['From'] ?? null,
                    'subject' => $payload['Subject'] ?? null,
                ]);
                return response()->noContent();
            }

            // 2) Verify inbound request (basic auth + signature if configured)
            $connection = $channel->providerConnection;
            $creds = is_array($connection?->credentials) ? $connection->credentials : [];

            $this->verifyBasicAuthIfConfigured($request, $creds);
            $this->verifyPostmarkSignatureIfConfigured($request, $raw, $creds);

            // 3) Conversation token
            $token = $request->header('X-Conversation-Token');
            if (!$token && isset($payload['TextBody'])) {
                preg_match('/\[conv:([A-Z0-9]{26})]/', (string) $payload['TextBody'], $m);
                $token = $m[1] ?? null;
            }

            // 4) Thread (token per channel)
            $thread = CommsEmailThread::query()->firstOrCreate(
                [
                    'comms_channel_id' => $channel->id,
                    'token' => $token ?: str()->ulid()->toBase32(),
                ],
                [
                    'team_id' => $channel->team_id,
                    'subject' => $payload['Subject'] ?? null,
                ]
            );

            // 5) Save inbound mail
            $addr = static function ($rawValue) {
                return match (true) {
                    is_null($rawValue) => null,
                    is_string($rawValue) => $rawValue,
                    default => collect($rawValue)->pluck('Email')->implode(','),
                };
            };

            $mail = $thread->inboundMails()->create([
                'postmark_id' => $postmarkId,
                'from' => $payload['From'] ?? null,
                'to' => $addr($payload['ToFull'] ?? $payload['To'] ?? null),
                'cc' => $addr($payload['CcFull'] ?? null),
                'reply_to' => $addr($payload['ReplyTo'] ?? null),
                'subject' => $payload['Subject'] ?? null,
                'html_body' => $payload['HtmlBody'] ?? null,
                'text_body' => $payload['TextBody'] ?? null,
                'headers' => $payload['Headers'] ?? null,
                'attachments_payload' => $payload['Attachments'] ?? null,
                'spam_score' => $payload['SpamScore'] ?? null,
                'received_at' => now(),
            ]);

            // 6) Persist attachments (UI/preview support later)
            foreach ($payload['Attachments'] ?? [] as $a) {
                $name = (string) ($a['Name'] ?? 'attachment');
                $content = (string) ($a['Content'] ?? '');
                if ($content === '') {
                    continue;
                }

                $path = "threads/{$thread->id}/{$name}";
                Storage::disk('emails')->put($path, base64_decode($content));

                $mail->attachments()->create([
                    'filename' => $name,
                    'mime' => (string) ($a['ContentType'] ?? 'application/octet-stream'),
                    'size' => (int) ($a['ContentLength'] ?? Storage::disk('emails')->size($path)),
                    'disk' => 'emails',
                    'path' => $path,
                    'cid' => $a['ContentID'] ?? null,
                    'inline' => isset($a['ContentID']),
                ]);
            }

            // 7) Optional: record generic inbound activity (if shared comms is installed)
            if (class_exists(CommsActivityService::class) && CommsActivityService::enabled()) {
                CommsActivityService::recordInbound(
                    channelId: 'email:' . $channel->id,
                    contextType: 'comms.channel',
                    contextId: (int) $channel->id,
                    teamId: (int) $channel->team_id,
                    threadRef: 'email-thread:' . $thread->id,
                    summary: (string) ($payload['Subject'] ?? 'Ohne Betreff'),
                    payload: [
                        'from' => $payload['From'] ?? null,
                        'to' => $payload['To'] ?? null,
                        'thread_id' => $thread->id,
                        'mail_id' => $mail->id,
                    ],
                    occurredAt: $mail->received_at ?? now(),
                );
            }

            return response()->noContent();
        } catch (\Throwable $e) {
            Log::error('Postmark inbound processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 204 to avoid retries/duplicates
            return response()->noContent();
        }
    }

    private function findChannelByRecipients(array $payload): ?CommsChannel
    {
        $recipients = $this->extractRecipients($payload);
        foreach ($recipients as $recipient) {
            $channel = CommsChannel::query()
                ->where('type', 'email')
                ->where('sender_identifier', $recipient)
                ->where('is_active', true)
                ->with('providerConnection')
                ->first();
            if ($channel) {
                return $channel;
            }
        }
        return null;
    }

    private function extractRecipients(array $payload): array
    {
        $recipients = [];

        if (isset($payload['To'])) {
            if (is_string($payload['To'])) {
                $recipients[] = $payload['To'];
            } elseif (is_array($payload['To'])) {
                $recipients = array_merge($recipients, collect($payload['To'])->pluck('Email')->toArray());
            }
        }

        if (isset($payload['ToFull'])) {
            $recipients = array_merge($recipients, collect($payload['ToFull'])->pluck('Email')->toArray());
        }

        return array_values(array_unique(array_filter(array_map('trim', $recipients))));
    }

    private function verifyBasicAuthIfConfigured(Request $request, array $creds): void
    {
        $user = (string) ($creds['inbound_user'] ?? '');
        $pass = (string) ($creds['inbound_pass'] ?? '');
        if ($user === '' && $pass === '') {
            return;
        }

        $expected = 'Basic ' . base64_encode("{$user}:{$pass}");
        $actual = (string) $request->header('Authorization');

        abort_unless(hash_equals($expected, $actual), 401, 'Invalid Postmark inbound credentials.');
    }

    private function verifyPostmarkSignatureIfConfigured(Request $request, string $rawBody, array $creds): void
    {
        $secret = (string) ($creds['signing_secret'] ?? '');
        if ($secret === '') {
            return;
        }

        $signature = (string) ($request->header('X-Postmark-Signature') ?? '');
        abort_unless($signature !== '', 401, 'Missing Postmark signature.');

        $computed = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        abort_unless(hash_equals($computed, $signature), 401, 'Invalid Postmark signature.');
    }
}

