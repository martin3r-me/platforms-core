<?php

namespace Platform\Core\Services;

use Platform\Core\Models\User;
use Platform\Core\Models\MicrosoftOAuthToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamsGraphService
{
    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';

    /**
     * Holt Access Token für einen User (aus Header → Session → DB → Refresh)
     * Kopiert das Pattern aus MicrosoftGraphCalendarService.
     */
    protected function getAccessToken(User $user): ?string
    {
        // 1. Aus Request Header
        if (request()->hasHeader('X-Microsoft-Access-Token')) {
            $token = request()->header('X-Microsoft-Access-Token');
            $this->saveToken($user, $token);
            return $token;
        }

        // 2. Aus Session
        if (session()->has('microsoft_access_token_' . $user->id)) {
            $token = session('microsoft_access_token_' . $user->id);
            $this->saveToken($user, $token);
            return $token;
        }

        // 3. Aus Datenbank
        $tokenModel = MicrosoftOAuthToken::where('user_id', $user->id)->first();

        if ($tokenModel) {
            if (!$tokenModel->isExpired()) {
                $token = $tokenModel->access_token;
                if ($token) {
                    return $token;
                }
            }

            // 4. Token Refresh
            if ($tokenModel->refresh_token) {
                $newToken = $this->refreshToken($user, $tokenModel);
                if ($newToken) {
                    return $newToken;
                }
            }
        }

        Log::warning('TeamsGraph: No valid token available', ['user_id' => $user->id]);
        return null;
    }

    protected function saveToken(User $user, string $token, ?string $refreshToken = null, ?int $expiresIn = null): void
    {
        try {
            $existingToken = MicrosoftOAuthToken::where('user_id', $user->id)->first();
            $scopes = $existingToken?->scopes ?? [
                'User.Read',
                'Calendars.ReadWrite',
                'Calendars.ReadWrite.Shared',
                'Team.ReadBasic.All',
                'Channel.ReadBasic.All',
                'ChannelMessage.Send',
                'Chat.ReadWrite',
                'ChatMessage.Send',
            ];

            MicrosoftOAuthToken::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $token,
                    'refresh_token' => $refreshToken ?? $existingToken?->refresh_token,
                    'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : ($existingToken?->expires_at ?? now()->addHour()),
                    'scopes' => $scopes,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('TeamsGraph: Failed to save token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function refreshToken(User $user, MicrosoftOAuthToken $tokenModel): ?string
    {
        if (!$tokenModel->refresh_token) {
            return null;
        }

        try {
            $tenant = config('services.microsoft.tenant', 'common');
            $clientId = config('services.microsoft.client_id');
            $clientSecret = config('services.microsoft.client_secret');

            if (!$clientId || !$clientSecret) {
                Log::error('TeamsGraph: OAuth credentials not configured', ['user_id' => $user->id]);
                return null;
            }

            $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $tokenModel->refresh_token,
                'grant_type' => 'refresh_token',
                'scope' => 'offline_access https://graph.microsoft.com/.default',
            ]);

            if (!$response->successful()) {
                Log::error('TeamsGraph: Token refresh failed', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $newAccessToken = $data['access_token'] ?? null;
            $newRefreshToken = $data['refresh_token'] ?? $tokenModel->refresh_token;
            $expiresIn = $data['expires_in'] ?? 3600;

            if (!$newAccessToken) {
                return null;
            }

            $this->saveToken($user, $newAccessToken, $newRefreshToken, $expiresIn);

            return $newAccessToken;
        } catch (\Throwable $e) {
            Log::error('TeamsGraph: Exception during token refresh', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Liste der Teams, in denen der User Mitglied ist.
     * GET /me/joinedTeams — Scope: Team.ReadBasic.All
     */
    public function getJoinedTeams(User $user): ?array
    {
        $token = $this->getAccessToken($user);
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get("{$this->baseUrl}/me/joinedTeams");

            if (!$response->successful()) {
                Log::error('TeamsGraph: Failed to get joined teams', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('value', []);
        } catch (\Throwable $e) {
            Log::error('TeamsGraph: Exception getting joined teams', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Liste der Channels eines Teams.
     * GET /teams/{team-id}/channels — Scope: Channel.ReadBasic.All
     */
    public function getChannels(User $user, string $teamId): ?array
    {
        $token = $this->getAccessToken($user);
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get("{$this->baseUrl}/teams/{$teamId}/channels");

            if (!$response->successful()) {
                Log::error('TeamsGraph: Failed to get channels', [
                    'user_id' => $user->id,
                    'team_id' => $teamId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('value', []);
        } catch (\Throwable $e) {
            Log::error('TeamsGraph: Exception getting channels', [
                'user_id' => $user->id,
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Nachricht in einen Channel senden.
     * POST /teams/{team-id}/channels/{channel-id}/messages — Scope: ChannelMessage.Send
     */
    public function sendChannelMessage(User $user, string $teamId, string $channelId, string $content, string $contentType = 'html'): ?array
    {
        $token = $this->getAccessToken($user);
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/teams/{$teamId}/channels/{$channelId}/messages", [
                'body' => [
                    'contentType' => $contentType,
                    'content' => $content,
                ],
            ]);

            if (!$response->successful()) {
                Log::error('TeamsGraph: Failed to send channel message', [
                    'user_id' => $user->id,
                    'team_id' => $teamId,
                    'channel_id' => $channelId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('TeamsGraph: Exception sending channel message', [
                'user_id' => $user->id,
                'team_id' => $teamId,
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Liste der letzten Chats des Users.
     * GET /me/chats — Scope: Chat.ReadWrite
     */
    public function getRecentChats(User $user, int $limit = 25): ?array
    {
        $token = $this->getAccessToken($user);
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get("{$this->baseUrl}/me/chats", [
                '$top' => $limit,
                '$orderby' => 'lastMessagePreview/createdDateTime desc',
            ]);

            if (!$response->successful()) {
                Log::error('TeamsGraph: Failed to get recent chats', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('value', []);
        } catch (\Throwable $e) {
            Log::error('TeamsGraph: Exception getting recent chats', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Nachricht in einen Chat senden.
     * POST /chats/{chat-id}/messages — Scope: ChatMessage.Send
     */
    public function sendChatMessage(User $user, string $chatId, string $content, string $contentType = 'html'): ?array
    {
        $token = $this->getAccessToken($user);
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/chats/{$chatId}/messages", [
                'body' => [
                    'contentType' => $contentType,
                    'content' => $content,
                ],
            ]);

            if (!$response->successful()) {
                Log::error('TeamsGraph: Failed to send chat message', [
                    'user_id' => $user->id,
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('TeamsGraph: Exception sending chat message', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Erstellt einen 1:1 Chat mit einem Empfänger.
     * POST /chats — Scope: Chat.ReadWrite
     */
    public function createOneOnOneChat(User $user, string $recipientEmail): ?array
    {
        $token = $this->getAccessToken($user);
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/chats", [
                'chatType' => 'oneOnOne',
                'members' => [
                    [
                        '@odata.type' => '#microsoft.graph.aadUserConversationMember',
                        'roles' => ['owner'],
                        'user@odata.bind' => "https://graph.microsoft.com/v1.0/users('{$user->email}')",
                    ],
                    [
                        '@odata.type' => '#microsoft.graph.aadUserConversationMember',
                        'roles' => ['owner'],
                        'user@odata.bind' => "https://graph.microsoft.com/v1.0/users('{$recipientEmail}')",
                    ],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('TeamsGraph: Failed to create 1:1 chat', [
                    'user_id' => $user->id,
                    'recipient' => $recipientEmail,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('TeamsGraph: Exception creating 1:1 chat', [
                'user_id' => $user->id,
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
