<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Command zum Erstellen von API-Tokens für Endpoint-Services (Nostradamus, Infoniqa)
 *
 * Erstellt automatisch einen Service-User, falls keiner existiert.
 *
 * Verwendung:
 * php artisan api:token:create-endpoint --name="Nostradamus Token" --show
 */
class CreateEndpointApiTokenCommand extends Command
{
    protected $signature = 'api:token:create-endpoint
                            {--name= : Name des Tokens (z.B. "Nostradamus Token")}
                            {--expires= : Ablaufdatum (30_days, 1_year, never)}
                            {--show : Token direkt anzeigen}';

    protected $description = 'Erstellt einen API-Token (Passport) für Endpoint-Services (erstellt automatisch Service-User)';

    /**
     * Service-User Email für Endpoint-Zugriffe
     */
    protected const SERVICE_USER_EMAIL = 'api-endpoint@service.local';

    public function handle(): int
    {
        $tokenName = $this->option('name') ?: 'Endpoint API Token';
        $expires = $this->option('expires') ?: 'never';

        // Service-User erstellen oder finden
        $user = $this->getOrCreateServiceUser();

        if (!$user) {
            $this->error('Service-User konnte nicht erstellt werden');
            return Command::FAILURE;
        }

        // Ablaufdatum berechnen
        $expiresAt = $this->calculateExpiresAt($expires);

        // Token erstellen (Passport)
        $tokenResult = $user->createToken($tokenName, ['*'], $expiresAt);
        $token = $tokenResult->accessToken;

        $this->info("API-Token erfolgreich erstellt!");
        $this->newLine();
        $this->line("Service-User: {$user->name} ({$user->email})");
        $this->line("Token Name: {$tokenName}");
        $this->line("Ablaufdatum: " . ($expiresAt ? $expiresAt->format('d.m.Y H:i') : 'Nie'));
        $this->newLine();

        if ($this->option('show')) {
            $this->line("Token:");
            $this->line($token);
        } else {
            $this->warn("Token wird nur einmal angezeigt!");
            $this->newLine();
            $this->line($token);
        }

        $this->newLine();
        $this->line("Verwendung in Endpoint-Services (config/platform.php):");
        $this->line("'api_token' => '{$token}',");

        return Command::SUCCESS;
    }

    /**
     * Berechnet das Ablaufdatum basierend auf der Option
     */
    protected function calculateExpiresAt(string $expires): ?\DateTimeInterface
    {
        return match ($expires) {
            '30_days' => now()->addDays(30),
            '1_year' => now()->addYear(),
            'never' => null,
            default => null,
        };
    }

    /**
     * Erstellt oder findet den Service-User für Endpoint-Zugriffe
     */
    protected function getOrCreateServiceUser(): ?User
    {
        $user = User::where('email', self::SERVICE_USER_EMAIL)->first();

        if (!$user) {
            $this->info('Erstelle Service-User für Endpoint-Zugriffe...');

            $user = User::create([
                'name' => 'API Endpoint Service User',
                'email' => self::SERVICE_USER_EMAIL,
                'password' => Hash::make(bin2hex(random_bytes(32))), // Zufälliges Passwort
                'email_verified_at' => now(),
            ]);

            // Personal Team für User erstellen (wie bei normalen Usern)
            \Platform\Core\PlatformCore::createPersonalTeamFor($user);

            $this->info("Service-User erstellt: {$user->email}");
        }

        return $user;
    }
}
