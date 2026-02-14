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
 */
class CreateMcpClientCommand extends Command
{
    protected $signature = 'mcp:client:create
                            {--name= : Name des MCP Clients (z.B. "Claude Code")}
                            {--redirect=http://127.0.0.1 : Redirect URI (default: localhost)}
                            {--public : Public Client ohne Secret (für PKCE)}';

    protected $description = 'Erstellt einen OAuth-Client für MCP Server (Claude Code, Cursor, etc.)';

    public function handle(ClientRepository $clientRepository): int
    {
        $name = $this->option('name') ?: $this->ask('Client Name', 'MCP Client');
        $redirect = $this->option('redirect');
        $isPublic = $this->option('public');

        // Client erstellen via Passport ClientRepository
        $client = $clientRepository->createAuthorizationCodeGrantClient(
            name: $name,
            redirectUris: [$redirect],
            confidential: !$isPublic,
        );

        $this->newLine();
        $this->info('MCP OAuth Client erstellt!');
        $this->newLine();

        $this->line("Client ID:     {$client->id}");

        if (!$isPublic && $client->plainSecret) {
            $this->warn("Client Secret: {$client->plainSecret}");
            $this->newLine();
            $this->error('⚠️  Das Secret wird nur EINMAL angezeigt! Jetzt speichern!');
        } else {
            $this->line('Client Secret: (Public Client - kein Secret)');
        }

        $this->newLine();
        $this->line('Konfiguration für Claude Code / MCP:');
        $this->line('URL: ' . config('app.url') . '/mcp/sse');

        return Command::SUCCESS;
    }
}
