<?php

namespace Platform\Core\Tools\Comms;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsWhatsAppThread;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\Comms\WhatsAppMetaService;
use Platform\Core\Tools\Comms\Concerns\ResolvesCommsRootTeam;

class SendWhatsAppMessageTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCommsRootTeam;

    public function getName(): string
    {
        return 'core.comms.whatsapp_messages.POST';
    }

    public function getDescription(): string
    {
        return 'POST /comms/whatsapp_messages – WhatsApp-Nachricht senden. NEUER THREAD: { comms_channel_id, to, body } (alle 3 erforderlich). REPLY: { thread_id, body } (to wird automatisch aus Thread abgeleitet). Optional: template_name, template_params, context_file_ids.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Es wird auf das Root-Team aufgelöst.',
                ],
                'comms_channel_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH für neuen Thread. Hole verfügbare WhatsApp-Channels mit core.comms.channels.GET (type=whatsapp).',
                ],
                'thread_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH für Reply. Ignoriere für neuen Thread (nutze comms_channel_id+to).',
                ],
                'to' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH für neuen Thread (Telefonnummer im E.164-Format, z.B. +491751234567). Bei Reply optional.',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => 'Nachrichtentext (Plaintext). Bei Template-Nachricht optional.',
                ],
                'template_name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name des WhatsApp-Templates. Bei Erstkontakt außerhalb des 24h-Fensters erforderlich.',
                ],
                'template_params' => [
                    'type' => 'array',
                    'description' => 'Optional: Template-Parameter als Array von Components. Format: [{type: "body", parameters: [{type: "text", text: "Wert"}]}]',
                ],
                'context_file_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Array von ContextFile-IDs, die als Medien gesendet werden sollen.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveRootTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeam = $resolved['team'];

            $body = trim((string) ($arguments['body'] ?? ''));
            $templateName = trim((string) ($arguments['template_name'] ?? ''));
            $templateParams = $arguments['template_params'] ?? [];
            $contextFileIds = $arguments['context_file_ids'] ?? [];

            // Validate: body or template required
            if ($body === '' && $templateName === '' && empty($contextFileIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'body, template_name oder context_file_ids ist erforderlich.');
            }

            $threadId = (int) ($arguments['thread_id'] ?? 0);
            $isReply = $threadId > 0;

            $channel = null;
            $thread = null;
            $to = null;

            if ($isReply) {
                $thread = CommsWhatsAppThread::query()
                    ->where('team_id', $rootTeam->id)
                    ->whereKey($threadId)
                    ->first();
                if (!$thread) {
                    return ToolResult::error('NOT_FOUND', 'Thread nicht gefunden.');
                }

                $channel = CommsChannel::query()
                    ->where('team_id', $rootTeam->id)
                    ->whereKey($thread->comms_channel_id)
                    ->first();
                if (!$channel) {
                    return ToolResult::error('NOT_FOUND', 'Channel zum Thread nicht gefunden.');
                }

                $to = $thread->remote_phone_number;
            } else {
                $channelId = (int) ($arguments['comms_channel_id'] ?? 0);
                if ($channelId <= 0) {
                    return ToolResult::error('VALIDATION_ERROR', 'Für einen neuen Thread: comms_channel_id (hole mit core.comms.channels.GET, filter type=whatsapp), to, body.');
                }

                $channel = CommsChannel::query()
                    ->where('team_id', $rootTeam->id)
                    ->whereKey($channelId)
                    ->first();
                if (!$channel) {
                    return ToolResult::error('NOT_FOUND', 'Channel nicht gefunden.');
                }

                $to = trim((string) ($arguments['to'] ?? ''));
                if ($to === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'Neuer Thread: to (Telefonnummer) ist erforderlich.');
                }
            }

            // Validate channel type
            if ($channel->type !== 'whatsapp' || $channel->provider !== 'whatsapp_meta') {
                return ToolResult::error('VALIDATION_ERROR', 'Channel ist kein WhatsApp-Channel (type=whatsapp, provider=whatsapp_meta).');
            }

            // Access: team channels usable by all; private channel only creator (or admin).
            if ($channel->visibility === 'private' && (int) $channel->created_by_user_id !== (int) $context->user->id) {
                if (!$this->isRootTeamAdmin($context, $rootTeam)) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen privaten Kanal.');
                }
            }

            /** @var WhatsAppMetaService $service */
            $service = app(WhatsAppMetaService::class);
            $message = null;

            // Send media files first if provided
            if (!empty($contextFileIds)) {
                foreach ($contextFileIds as $fileId) {
                    $file = ContextFile::query()
                        ->where('team_id', $rootTeam->id)
                        ->whereKey($fileId)
                        ->first();
                    if ($file) {
                        $message = $service->sendMedia($channel, $to, $file, $body ?: null, $context->user);
                        $body = ''; // Only send caption with first media
                    }
                }
            }

            // Send template if specified
            if ($templateName !== '' && !$message) {
                $message = $service->sendTemplate($channel, $to, $templateName, $templateParams, 'de', $context->user);
            }

            // Send text message if no template and no media sent yet
            if ($body !== '' && !$message) {
                $message = $service->sendText($channel, $to, $body, $context->user);
            }

            if (!$message) {
                return ToolResult::error('SEND_FAILED', 'Nachricht konnte nicht gesendet werden.');
            }

            // Apply context from ToolContext metadata (e.g., AutoPilot -> Applicant)
            $resultThread = $message->thread;
            if ($resultThread && !$resultThread->context_model) {
                $ctxModel = $context->metadata['context_model'] ?? null;
                $ctxModelId = $context->metadata['context_model_id'] ?? null;
                if ($ctxModel && $ctxModelId) {
                    $resultThread->update([
                        'context_model' => $ctxModel,
                        'context_model_id' => $ctxModelId,
                    ]);
                }
            }

            return ToolResult::success([
                'message' => 'WhatsApp-Nachricht gesendet.',
                'whatsapp_message' => [
                    'id' => (int) $message->id,
                    'thread_id' => (int) $message->comms_whatsapp_thread_id,
                    'direction' => $message->direction,
                    'status' => $message->status,
                    'message_type' => $message->message_type,
                    'sent_at' => $message->sent_at?->toIso8601String(),
                ],
                'thread' => $resultThread ? [
                    'id' => (int) $resultThread->id,
                    'comms_channel_id' => (int) $resultThread->comms_channel_id,
                    'remote_phone_number' => $resultThread->remote_phone_number,
                ] : null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Senden der WhatsApp-Nachricht: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['comms', 'whatsapp', 'send', 'outbound'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
