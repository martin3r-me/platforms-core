<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\User;
use Illuminate\Support\Str;

/**
 * Command zum Erstellen von API-Tokens für externe Services
 * 
 * Verwendung:
 * php artisan api:token:create --email=user@example.com --name="Datawarehouse Token"
 */
class CreateApiTokenCommand extends Command
{
    protected $signature = 'api:token:create 
                            {--email= : E-Mail des Users}
                            {--name= : Name des Tokens (z.B. "Datawarehouse Token")}
                            {--user-id= : User ID (alternativ zu email)}
                            {--show : Token direkt anzeigen}';

    protected $description = 'Erstellt einen API-Token für einen User (z.B. für Datawarehouse)';

    public function handle(): int
    {
        $email = $this->option('email');
        $userId = $this->option('user-id');
        $tokenName = $this->option('name') ?: 'API Token';

        // User finden
        if ($userId) {
            $user = User::find($userId);
        } elseif ($email) {
            $user = User::where('email', $email)->first();
        } else {
            $this->error('Bitte --email oder --user-id angeben');
            return Command::FAILURE;
        }

        if (!$user) {
            $this->error('User nicht gefunden');
            return Command::FAILURE;
        }

        // Token erstellen
        $token = $user->createToken($tokenName)->plainTextToken;

        $this->info("✓ API-Token erfolgreich erstellt!");
        $this->newLine();
        $this->line("User: {$user->name} ({$user->email})");
        $this->line("Token Name: {$tokenName}");
        $this->newLine();

        if ($this->option('show')) {
            $this->line("Token:");
            $this->line($token);
        } else {
            $this->warn("⚠ Token wird nur einmal angezeigt!");
            $this->line("Token: {$token}");
        }

        $this->newLine();
        $this->line("Verwendung im Datawarehouse (.env):");
        $this->line("PLATFORM_API_TOKEN={$token}");

        return Command::SUCCESS;
    }
}

