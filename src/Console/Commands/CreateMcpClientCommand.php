<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;

/**
 * Command zum Erstellen von OAuth-Clients für MCP Server (Claude Code, Cursor, etc.)
 *
 * Verwendung:
 * php artisan mcp:client:create --name="Claude Code" --redirect=http://127.0.0.1
 * php artisan mcp:client:create --name="Cursor" --public
 * php artisan mcp:client:create --claude-ai  # Erstellt Client für Claude.ai Web
 */
class CreateMcpClientCommand extends Command
{
    protected $signature = 'mcp:client:create
                            {--name= : Name des MCP Clients (z.B. "Claude Code")}
                            {--redirect=http://127.0.0.1 : Redirect URI (default: localhost)}
                            {--public : Public Client ohne Secret (für PKCE)}
                            {--claude-ai : Erstellt Public Client für Claude.ai Web Connector}';

    protected $description = 'Erstellt einen OAuth-Client für MCP Server (Claude Code, Cursor, etc.)';

    /**
     * Bekannte Redirect URIs für Claude.ai Web Connector
     */
    protected array $claudeAiRedirectUris = [
        'https://claude.ai/api/auth/callback',
        'https://claude.ai/api/mcp/auth/callback',
        'http://127.0.0.1',
    ];

    public function handle(ClientRepository $clientRepository): int
    {
        $isClaudeAi = $this->option('claude-ai');
        $isPublic = $this->option('public') || $isClaudeAi; // Claude.ai always public

        if ($isClaudeAi) {
            $name = 'Claude.ai Web Connector';
            $redirectUris = $this->claudeAiRedirectUris;
            $this->info('Erstelle Public Client für Claude.ai Web Connector...');
        } else {
            $name = $this->option('name') ?: $this->ask('Client Name', 'MCP Client');
            $redirectUris = [$this->option('redirect')];
        }

        // Client erstellen via Passport ClientRepository
        $client = $clientRepository->createAuthorizationCodeGrantClient(
            name: $name,
            redirectUris: $redirectUris,
            confidential: !$isPublic,
        );

        $this->newLine();
        $this->info('MCP OAuth Client erstellt!');
        $this->newLine();

        $this->line("Client ID:     {$client->id}");

        if (!$isPublic && $client->plainSecret) {
            $this->warn("Client Secret: {$client->plainSecret}");
            $this->newLine();
            $this->error('Das Secret wird nur EINMAL angezeigt! Jetzt speichern!');
        } else {
            $this->line('Client Secret: (Public Client - kein Secret, verwendet PKCE)');
        }

        $this->newLine();
        $this->line('Redirect URIs: ' . implode(', ', $redirectUris));

        $this->newLine();
        $this->line('Konfiguration für MCP:');
        $this->line('URL: ' . config('app.url') . '/mcp/sse');

        if ($isClaudeAi) {
            $this->newLine();
            $this->info('Nächste Schritte für Claude.ai:');
            $this->line('1. Alten Connector in Claude.ai löschen (falls vorhanden)');
            $this->line('2. Neuen Connector hinzufügen mit URL: ' . config('app.url') . '/mcp/sse');
            $this->line('3. OAuth Flow durchführen - KEIN Secret eingeben (Public Client)');
        }

        return Command::SUCCESS;
    }
}
