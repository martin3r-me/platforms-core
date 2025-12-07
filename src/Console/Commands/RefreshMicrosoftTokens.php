<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Platform\Core\Models\MicrosoftOAuthToken;

class RefreshMicrosoftTokens extends Command
{
    protected $signature = 'core:refresh-microsoft-tokens 
                            {--user= : Nur für eine bestimmte User-ID} 
                            {--force : Auch wenn Token noch nicht abläuft}';

    protected $description = 'Aktualisiert abgelaufene oder bald ablaufende Microsoft OAuth Tokens über den Refresh Token';

    public function handle(): int
    {
        $userId = $this->option('user');
        $force = (bool) $this->option('force');

        $tenant = config('services.microsoft.tenant', 'common');
        $clientId = config('services.microsoft.client_id');
        $clientSecret = config('services.microsoft.client_secret');

        if (!$clientId || !$clientSecret) {
            $this->error('Microsoft OAuth ist nicht konfiguriert (client_id / client_secret fehlen).');
            return Command::FAILURE;
        }

        $query = MicrosoftOAuthToken::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '<=', now())
                  ->orWhere('expires_at', '<=', now()->addMinutes(10));
            });
        }

        $tokens = $query->get();

        if ($tokens->isEmpty()) {
            $this->info('Keine (bald) ablaufenden Tokens gefunden.');
            return Command::SUCCESS;
        }

        $this->info("Prüfe {$tokens->count()} Token(s)...");

        $refreshEndpoint = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";
        $updated = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            if (!$token->refresh_token) {
                $this->warn("User {$token->user_id}: kein Refresh Token vorhanden.");
                $failed++;
                continue;
            }

            $this->line("User {$token->user_id}: versuche Refresh...");

            $response = Http::asForm()->post($refreshEndpoint, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'offline_access https://graph.microsoft.com/.default',
            ]);

            if (!$response->ok()) {
                $this->warn("  ✗ Refresh fehlgeschlagen (Status {$response->status()}): {$response->body()}");
                $failed++;
                continue;
            }

            $data = $response->json();
            $accessToken = $data['access_token'] ?? null;
            $newRefresh = $data['refresh_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? null;

            if (!$accessToken) {
                $this->warn("  ✗ Keine access_token im Response erhalten.");
                $failed++;
                continue;
            }

            $token->access_token = $accessToken;
            if ($newRefresh) {
                $token->refresh_token = $newRefresh;
            }
            if ($expiresIn) {
                $token->expires_at = now()->addSeconds((int) $expiresIn);
            }
            $token->save();

            $updated++;
            $this->info("  ✓ Token aktualisiert (läuft ab: {$token->expires_at?->format('d.m.Y H:i:s')})");
        }

        $this->info("Fertig. Erfolgreich: {$updated}, Fehlgeschlagen: {$failed}");

        return Command::SUCCESS;
    }
}

