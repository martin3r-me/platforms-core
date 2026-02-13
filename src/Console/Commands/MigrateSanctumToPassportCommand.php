<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\User;

/**
 * Command zum Migrieren von Sanctum-Tokens zu Passport-Tokens
 *
 * WICHTIG: Die neuen Passport-Tokens müssen in allen externen Systemen
 * (Datawarehouse, MCP-Server, etc.) aktualisiert werden!
 *
 * Verwendung:
 * php artisan passport:migrate-from-sanctum --dry-run  # Vorschau
 * php artisan passport:migrate-from-sanctum            # Migration ausführen
 */
class MigrateSanctumToPassportCommand extends Command
{
    protected $signature = 'passport:migrate-from-sanctum
                            {--dry-run : Zeigt nur an, was migriert werden würde}
                            {--keep-sanctum : Behält die alten Sanctum-Tokens}';

    protected $description = 'Migriert bestehende Sanctum Personal Access Tokens zu Passport';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $keepSanctum = $this->option('keep-sanctum');

        $this->info('=== Sanctum zu Passport Token-Migration ===');
        $this->newLine();

        // Prüfe ob Sanctum-Tabelle existiert
        if (!$this->sanctumTableExists()) {
            $this->warn('Sanctum personal_access_tokens Tabelle existiert nicht.');
            $this->info('Keine Migration notwendig.');
            return Command::SUCCESS;
        }

        // Lade alle Sanctum-Tokens
        $sanctumTokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'like', '%User%')
            ->get();

        if ($sanctumTokens->isEmpty()) {
            $this->info('Keine Sanctum-Tokens gefunden. Migration abgeschlossen.');
            return Command::SUCCESS;
        }

        $this->info("Gefundene Sanctum-Tokens: {$sanctumTokens->count()}");
        $this->newLine();

        // Gruppiere nach User
        $tokensByUser = $sanctumTokens->groupBy('tokenable_id');

        $migratedTokens = [];

        foreach ($tokensByUser as $userId => $tokens) {
            $user = User::find($userId);

            if (!$user) {
                $this->warn("User ID {$userId} nicht gefunden - übersprungen");
                continue;
            }

            $this->line("User: {$user->name} ({$user->email})");

            foreach ($tokens as $sanctumToken) {
                $this->line("  - Token: {$sanctumToken->name}");
                $this->line("    Erstellt: " . ($sanctumToken->created_at ?? 'unbekannt'));
                $this->line("    Zuletzt verwendet: " . ($sanctumToken->last_used_at ?? 'nie'));

                if (!$dryRun) {
                    // Neuen Passport-Token erstellen
                    $expiresAt = $sanctumToken->expires_at
                        ? \Carbon\Carbon::parse($sanctumToken->expires_at)
                        : null;

                    $tokenResult = $user->createToken($sanctumToken->name, ['*'], $expiresAt);

                    $migratedTokens[] = [
                        'user_id' => $userId,
                        'user_email' => $user->email,
                        'token_name' => $sanctumToken->name,
                        'old_token_id' => $sanctumToken->id,
                        'new_token' => $tokenResult->accessToken,
                    ];

                    $this->info("    -> Neuer Passport-Token erstellt");
                }
            }
            $this->newLine();
        }

        if ($dryRun) {
            $this->warn('=== DRY-RUN MODUS - Keine Änderungen vorgenommen ===');
            $this->newLine();
            $this->info('Führen Sie den Befehl ohne --dry-run aus, um die Migration durchzuführen.');
            return Command::SUCCESS;
        }

        // Neue Tokens ausgeben
        if (!empty($migratedTokens)) {
            $this->newLine();
            $this->warn('=== WICHTIG: Neue Tokens für externe Systeme ===');
            $this->warn('Diese Tokens müssen in den externen Systemen aktualisiert werden!');
            $this->newLine();

            foreach ($migratedTokens as $token) {
                $this->line("User: {$token['user_email']}");
                $this->line("Token Name: {$token['token_name']}");
                $this->line("Neuer Token:");
                $this->line($token['new_token']);
                $this->newLine();
                $this->line(str_repeat('-', 80));
                $this->newLine();
            }
        }

        // Sanctum-Tokens löschen (optional)
        if (!$keepSanctum) {
            $deletedCount = DB::table('personal_access_tokens')
                ->where('tokenable_type', 'like', '%User%')
                ->delete();

            $this->info("Alte Sanctum-Tokens gelöscht: {$deletedCount}");
        } else {
            $this->info('Alte Sanctum-Tokens wurden beibehalten (--keep-sanctum)');
        }

        $this->newLine();
        $this->info('=== Migration abgeschlossen ===');
        $this->warn('Vergessen Sie nicht, die neuen Tokens in allen externen Systemen zu konfigurieren:');
        $this->line('- HCM Module (Nostradamus)');
        $this->line('- Planner Module');
        $this->line('- Organization Module');
        $this->line('- OKR Module');
        $this->line('- MCP Server');
        $this->line('- Weitere Datawarehouse-Integrationen');

        return Command::SUCCESS;
    }

    /**
     * Prüft ob die Sanctum-Tabelle existiert
     */
    protected function sanctumTableExists(): bool
    {
        return DB::getSchemaBuilder()->hasTable('personal_access_tokens');
    }
}
