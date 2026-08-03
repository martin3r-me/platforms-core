<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Core\Models\User;

/**
 * Agent-Verzeichnis: der Auswahl-Pool an Kollegen für einen Worker (z. B. der „betreute User"
 * eines Assistenten). Ein Token authentifiziert einen USER (mit current_team als Fokus, aber
 * Mitglied MEHRERER Teams) — die Liste läuft daher bewusst über ALLE Teams des Users, nicht nur
 * das aktive. Authz-Grenze = genau diese Teams (kein Leck über fremde User). Der Worker selbst
 * ist ausgenommen.
 */
class AgentUsersController extends Controller
{
    /**
     * GET /api/core/agent/users
     * → [{ id, name, email, teams: [{id, name}] }]
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $teamIds = $user->teams()->pluck('teams.id')->all();
        if (empty($teamIds)) {
            return response()->json(['data' => []]);
        }

        $members = User::query()
            ->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds))
            ->where('id', '!=', $user->id)
            ->with(['teams' => fn ($q) => $q->whereIn('teams.id', $teamIds)])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $members->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'teams' => $u->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            ])->values(),
        ]);
    }
}
