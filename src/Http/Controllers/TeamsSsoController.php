<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\AuthAccessPolicy;

class TeamsSsoController extends Controller
{
    public function authenticate(Request $request)
    {
        // Teams JS SDK Token erhalten
        $teamsToken = $request->input('access_token');
        
        if (!$teamsToken) {
            return response()->json([
                'error' => 'No access token provided',
                'auth_url' => route('azure-sso.login')
            ], 401);
        }

        try {
            // Microsoft Graph API Token validieren
            $userInfo = $this->validateMicrosoftToken($teamsToken);
            
            if (!$userInfo) {
                return response()->json([
                    'error' => 'Invalid token',
                    'auth_url' => route('azure-sso.login')
                ], 401);
            }

            // AuthAccessPolicy prüfen
            /** @var AuthAccessPolicy $policy */
            $policy = app(AuthAccessPolicy::class);
            
            if (!$policy->isEmailAllowed($userInfo['email'])) {
                Log::warning('Teams SSO: Email not allowed', ['email' => $userInfo['email']]);
                return response()->json([
                    'error' => 'Access denied',
                    'auth_url' => route('azure-sso.login')
                ], 403);
            }

            // User finden oder erstellen
            $user = $this->findOrCreateUser($userInfo);
            
            if (!$user) {
                return response()->json([
                    'error' => 'User creation failed',
                    'auth_url' => route('azure-sso.login')
                ], 500);
            }

            // User authentifizieren
            Auth::login($user, true);
            
            Log::info('Teams SSO: User authenticated successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ],
                'redirect_url' => $request->input('redirect_url', '/')
            ]);

        } catch (\Throwable $e) {
            Log::error('Teams SSO authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Authentication failed',
                'auth_url' => route('azure-sso.login')
            ], 500);
        }
    }

    public function getAuthStatus(Request $request)
    {
        if (Auth::check()) {
            return response()->json([
                'authenticated' => true,
                'user' => [
                    'id' => Auth::id(),
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email
                ]
            ]);
        }

        return response()->json([
            'authenticated' => false,
            'auth_url' => route('azure-sso.login')
        ]);
    }

    private function validateMicrosoftToken(string $token): ?array
    {
        try {
            $response = \Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->get('https://graph.microsoft.com/v1.0/me');

            if (!$response->successful()) {
                Log::warning('Teams SSO: Microsoft Graph API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            
            return [
                'id' => $data['id'] ?? null,
                'email' => $data['mail'] ?? $data['userPrincipalName'] ?? null,
                'name' => $data['displayName'] ?? null,
            ];

        } catch (\Throwable $e) {
            Log::error('Teams SSO: Token validation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function findOrCreateUser(array $userInfo)
    {
        $userModelClass = config('auth.providers.users.model');
        
        $user = $userModelClass::query()
            ->where('email', $userInfo['email'])
            ->orWhere('azure_id', $userInfo['id'])
            ->first();

        if (!$user) {
            $user = new $userModelClass();
            $user->email = $userInfo['email'];
            $user->name = $userInfo['name'] ?? $userInfo['email'];
            $user->azure_id = $userInfo['id'];
            $user->save();
            
            // Personal Team erstellen
            \Platform\Core\PlatformCore::createPersonalTeamFor($user);
        }

        return $user;
    }
}
