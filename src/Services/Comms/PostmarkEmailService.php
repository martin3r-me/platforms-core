<?php

namespace Platform\Core\Services\Comms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailOutboundMail;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Models\CommsProviderConnection;
use Platform\Core\Models\User;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkAttachment;

class PostmarkEmailService
{
    public function send(
        CommsChannel $channel,
        string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $files = [],
        array $opt = [],
    ): string {
        if ($channel->type !== 'email' || $channel->provider !== 'postmark') {
            throw new \InvalidArgumentException('Channel must be type=email and provider=postmark.');
        }

        $channel->loadMissing('providerConnection');
        $connection = $channel->providerConnection ?: CommsProviderConnection::query()->find($channel->comms_provider_connection_id);
        $creds = is_array($connection?->credentials) ? $connection->credentials : [];
        $serverToken = (string) ($creds['server_token'] ?? '');
        if ($serverToken === '') {
            throw new \RuntimeException('Missing Postmark server_token in provider connection.');
        }

        $client = new PostmarkClient($serverToken);

        // 1) Thread & Token
        $token = $opt['token'] ?? Str::ulid()->toBase32();

        $thread = CommsEmailThread::query()->firstOrCreate(
            [
                'comms_channel_id' => $channel->id,
                'token' => $token,
            ],
            [
                'team_id' => $channel->team_id,
                'subject' => $subject,
            ]
        );

        // 2) Re: prefix for replies
        if (($opt['is_reply'] ?? false) && !preg_match('/^Re:/i', $subject)) {
            $subject = 'Re: ' . $subject;
        }

        // 3) Marker & (optional) signature
        $signatureHtml = '';
        $signatureText = '';
        if (($opt['sender'] ?? null) instanceof User) {
            $sigName = $opt['sender']->fullname
                ?? trim(($opt['sender']->first_name ?? '') . ' ' . ($opt['sender']->last_name ?? ''))
                ?: null;
            if ($sigName) {
                $signatureHtml = "<br><br><p style=\"font-size: 13px; color: #444; margin: 0;\">&ndash;&ndash;<br>{$sigName}</p>";
                $signatureText = "\n\n--\n{$sigName}";
            }
        }

        $marker = "[conv:$token]";
        $htmlBody .= $signatureHtml;
        $htmlBody .= "\n<!-- conversation-token:$token --><span style=\"display:block;\">$marker</span>";

        $textBody ??= strip_tags($htmlBody);
        $textBody .= $signatureText;
        $textBody .= "\n\n$marker";

        // 4) Attachments
        $pmAttachments = [];
        $storedAttachments = [];

        foreach ($files as $file) {
            $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
            if (!is_string($path) || !is_file($path) || filesize($path) === 0) {
                continue;
            }

            $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($path);
            $mime = $file instanceof UploadedFile ? $file->getClientMimeType() : mime_content_type($path);

            $pmAttachments[] = PostmarkAttachment::fromFile($path, $name, $mime ?: 'application/octet-stream');

            if ($file instanceof UploadedFile) {
                Storage::disk('emails')->putFileAs("threads/{$thread->id}", $file, $name);
                $storedPath = "threads/{$thread->id}/{$name}";
            } else {
                $storedPath = $path;
            }

            $storedAttachments[] = [
                'name' => $name,
                'mime' => $mime,
                'storedPath' => $storedPath,
            ];
        }

        $pmAttachments = $pmAttachments ?: null;

        // 5) Send via Postmark (always send conversation token header)
        // NOTE: postmark-php expects an associative array; it converts to [{Name,Value}, ...] internally.
        // Build RFC 5322 compliant headers for best deliverability
        $senderEmail = $this->extractEmailAddress($channel->sender_identifier) ?: $channel->sender_identifier;
        $messageIdDomain = substr(strrchr($senderEmail, '@'), 1) ?: 'postmark';
        
        // Generate RFC 5322 compliant Message-ID: <unique-id@domain>
        // Format: timestamp + ULID for uniqueness, using sender domain for better alignment
        $messageId = '<' . time() . '.' . Str::ulid()->toBase32() . '@' . $messageIdDomain . '>';
        
        // Best practice headers for deliverability (especially Microsoft 365/Outlook)
        // - Message-ID: Required for threading and spam filtering (RFC 5322)
        // - MIME-Version: Required when using MIME features (multipart, HTML, attachments)
        // - Date: Postmark sets this automatically, but explicit is better
        // Note: Content-Type is automatically set by Postmark based on HtmlBody/TextBody/Attachments
        $headersArray = [
            'X-Conversation-Token' => $token,
            'Message-ID' => $messageId,
            'MIME-Version' => '1.0',
        ];

        $fromName = null;
        if (($opt['sender'] ?? null) instanceof User) {
            $fromName = $opt['sender']->fullname
                ?? trim(($opt['sender']->first_name ?? '') . ' ' . ($opt['sender']->last_name ?? ''))
                ?: null;
        }
        $fromName ??= ($channel->name ?: null);
        $from = $fromName ? "{$fromName} <{$channel->sender_identifier}>" : $channel->sender_identifier;

        $client->sendEmail(
            $from,
            $to,
            $subject,
            $htmlBody,
            $textBody,
            $opt['tag'] ?? null,
            $opt['track_opens'] ?? true,
            $opt['reply_to'] ?? null,
            $opt['cc'] ?? null,
            $opt['bcc'] ?? null,
            $headersArray,
            $pmAttachments,
            $opt['track_links'] ?? null,
            $opt['metadata'] ?? null,
            null // message stream (optional)
        );

        // 6) Persist outbound mail
        $mail = CommsEmailOutboundMail::create([
            'thread_id' => $thread->id,
            'comms_channel_id' => $channel->id,
            'created_by_user_id' => (($opt['sender'] ?? null) instanceof User) ? $opt['sender']->id : null,
            'from' => $from,
            'to' => $to,
            'cc' => $opt['cc'] ?? null,
            'bcc' => $opt['bcc'] ?? null,
            'reply_to' => $opt['reply_to'] ?? null,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
            'meta' => [
                'token' => $token,
            ],
            'sent_at' => now(),
        ]);
        $thread->touch();

        // Thread rollups
        $thread->last_outbound_to = $to;
        $thread->last_outbound_to_address = $this->extractEmailAddress($to) ?: $to;
        $thread->last_outbound_at = $mail->sent_at ?? now();
        if (!$thread->subject) {
            $thread->subject = $subject;
        }
        $thread->save();

        foreach ($storedAttachments as $a) {
            $mail->attachments()->create([
                'filename' => $a['name'],
                'mime' => $a['mime'],
                'size' => Storage::disk('emails')->exists($a['storedPath'])
                    ? Storage::disk('emails')->size($a['storedPath'])
                    : null,
                'disk' => 'emails',
                'path' => $a['storedPath'],
                'inline' => false,
            ]);
        }

        return $token;
    }

    private function extractEmailAddress(string $raw): ?string
    {
        if (preg_match('/<([^>]+)>/', $raw, $m)) {
            return trim((string) ($m[1] ?? '')) ?: null;
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return $raw;
        }
        return null;
    }
}

