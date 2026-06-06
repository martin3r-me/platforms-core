<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Platform\Core\Models\UserUiPreference;

class UserUiPreferenceController extends Controller
{
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'state' => ['required', 'array'],
        ]);

        UserUiPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['state' => $validated['state']],
        );

        return response()->noContent();
    }

    public function show(Request $request): JsonResponse
    {
        $pref = UserUiPreference::where('user_id', $request->user()->id)->first();

        return response()->json([
            'state' => $pref?->state ?? new \stdClass(),
        ]);
    }
}
