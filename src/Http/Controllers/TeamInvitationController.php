<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Core\Services\TeamInvitationService;

class TeamInvitationController extends Controller
{
    public function accept(Request $request, string $token, TeamInvitationService $service)
    {
        if (! Auth::check()) {
            // Nach Login zurückkehren und Token erneut verarbeiten
            return redirect()->route('azure-sso.login', ['redirect' => route('team-invitations.accept', ['token' => $token])]);
        }

        $user = Auth::user();
        $ok = $service->acceptByToken($token, $user);

        if (! $ok) {
            return redirect()->route('platform.dashboard')->with('error', 'Einladung ungültig oder bereits verwendet.');
        }

        return redirect()->route('platform.dashboard')->with('success', 'Du bist dem Team beigetreten.');
    }
}


