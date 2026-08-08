<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Support\Presenter;

/**
 * Presenter-Push: blendet einen Echtzeit-Kommentar als Sprechblasen-Overlay in den
 * Browsern des aktuellen Teams ein — der Kanal fuer gefuehrte Live-Demos / Screencasts.
 */
class PresenterPushTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.presenter.POST';
    }

    public function getDescription(): string
    {
        return 'POST /presenter - Zeigt einen Echtzeit-Kommentar als Sprechblasen-Overlay in den Browsern '
            . 'des aktuellen Teams (Presenter-Kanal fuer gefuehrte Demos/Screencasts). '
            . 'REQUIRED: message. Optional: title, speaker (default "Claude"), duration (Sekunden, default 9).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message'  => ['type' => 'string', 'description' => 'Kommentar-Text (REQUIRED).'],
                'title'    => ['type' => 'string', 'description' => 'Optionale fette Ueberschrift ueber dem Text.'],
                'speaker'  => ['type' => 'string', 'description' => 'Sprecher-Label (default "Claude").'],
                'duration' => ['type' => 'integer', 'description' => 'Anzeigedauer in Sekunden (default 9, min 2).'],
            ],
            'required' => ['message'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $teamId = $context->team?->id ?? $context->user?->currentTeam?->id;
        if (!$teamId) {
            return ToolResult::error('NO_TEAM', 'Kein Team im Kontext.');
        }

        $message = trim((string) ($arguments['message'] ?? ''));
        if ($message === '') {
            return ToolResult::error('VALIDATION_ERROR', 'message ist erforderlich.');
        }

        $id = Presenter::push(
            (int) $teamId,
            $message,
            isset($arguments['title']) && $arguments['title'] !== '' ? (string) $arguments['title'] : null,
            isset($arguments['speaker']) && $arguments['speaker'] !== '' ? (string) $arguments['speaker'] : 'Claude',
            isset($arguments['duration']) ? max(2, (int) $arguments['duration']) : 9,
        );

        return ToolResult::success([
            'id'      => $id,
            'team_id' => (int) $teamId,
            'message' => $message,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['core', 'presenter', 'demo', 'overlay'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
