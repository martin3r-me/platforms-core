<?php

use Illuminate\Support\Facades\Broadcast;
use Platform\Core\Models\TerminalChannelMember;

Broadcast::channel('terminal.channel.{channelId}', function ($user, $channelId) {
    return TerminalChannelMember::where('channel_id', $channelId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('terminal.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('terminal.team.{teamId}', function ($user, $teamId) {
    if ($user->currentTeam?->id !== (int) $teamId) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
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
