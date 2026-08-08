<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Support\Presenter;

/**
 * Presenter-Push: ein Regie-Schritt (Kommentar + optionale Navigation) als Sprechblasen-
 * Overlay in den Browsern des aktuellen Teams. Bleibt stehen, bis der Zuschauer bestaetigt.
 * Der Kanal fuer gefuehrte Live-Demos / Onboarding / Screencasts.
 */
class PresenterPushTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.presenter.POST';
    }

    public function getDescription(): string
    {
        return 'POST /presenter - Zeigt einen Regie-Schritt als Sprechblasen-Overlay in den Browsern des '
            . 'aktuellen Teams (gefuehrte Demos/Screencasts). Der Schritt bleibt stehen, bis der Zuschauer '
            . '"Verstanden" klickt. REQUIRED: message. Optional: navigate (Pfad, z.B. "/encounter/appointments/9" '
            . '— beamt den Browser dorthin), title, speaker (default "Claude").';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message'  => ['type' => 'string', 'description' => 'Kommentar-Text (REQUIRED).'],
                'navigate' => ['type' => 'string', 'description' => 'Optionaler Pfad, zu dem der Zuschauer-Browser navigiert wird (z.B. "/encounter/appointments/9").'],
                'title'    => ['type' => 'string', 'description' => 'Optionale fette Ueberschrift ueber dem Text.'],
                'speaker'  => ['type' => 'string', 'description' => 'Sprecher-Label (default "Claude").'],
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
            isset($arguments['navigate']) && $arguments['navigate'] !== '' ? (string) $arguments['navigate'] : null,
        );

        return ToolResult::success([
            'id'       => $id,
            'team_id'  => (int) $teamId,
            'message'  => $message,
            'navigate' => $arguments['navigate'] ?? null,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['core', 'presenter', 'demo', 'overlay', 'tour'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
