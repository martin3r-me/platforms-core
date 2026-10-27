<?php

use Illuminate\Support\Facades\Broadcast;

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
