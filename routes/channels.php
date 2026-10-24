<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('canvas.workshop.{canvasId}', function ($user, $canvasId) {
    $canvas = \Platform\Canvas\Models\Canvas::find($canvasId);
    if (!$canvas || $canvas->team_id !== $user->currentTeam?->id) {
        return false;
    }

    return $canvas->isVisibleTo($user);
});

Broadcast::channel('page.{teamId}.{pageKey}', function ($user, $teamId, $pageKey) {
    if ($user->currentTeam?->id !== (int) $teamId) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->avatar ?? null,
    ];
});
