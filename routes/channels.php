<?php

use Illuminate\Support\Facades\Broadcast;
use Platform\Core\Models\TerminalChannelMember;

Broadcast::channel('terminal.channel.{channelId}', function ($user, $channelId) {
    return TerminalChannelMember::where('channel_id', $channelId)
        ->where('user_id', $user->id)
        ->exists();
});
