<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;
use Platform\Core\Models\CheckinTodo;
use Platform\Core\Support\FieldHasher;

class EncryptCheckinTodos extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'core:encrypt-checkin-todos 
                            {--dry-run : Zeigt nur an, was verschlüsselt würde, ohne Änderungen}';

    /**
     * The console command description.
     */
    protected $description = 'Verschlüsselt alle vorhandenen title Felder in checkin_todos';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY-RUN Modus - keine Daten werden geändert');
        }

        $this->info('🔐 Starte Verschlüsselung von Checkin-Todo-Titeln...');
        $this->newLine();

        // Prüfe ob Hash-Spalte existiert
        if (!Schema::hasColumn('checkin_todos', 'title_hash')) {
            $this->error('❌ Hash-Spalte existiert nicht. Bitte Migration zuerst ausführen: php artisan migrate');
            return Command::FAILURE;
        }

        // Todos mit nicht-leeren title finden
        $todos = CheckinTodo::query()
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->get();

        $total = $todos->count();

        if ($total === 0) {
            $this->info('✅ Keine Todos mit Titeln gefunden.');
            return Command::SUCCESS;
        }

        $this->info("📋 {$total} Todo(s) gefunden, die verschlüsselt werden müssen.");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $encrypted = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($todos as $todo) {
            try {
                $needsUpdate = false;
                $plainTitle = null;

                // Prüfe title - Raw-Wert direkt aus DB lesen
                $rawTitle = DB::table('checkin_todos')
                    ->where('id', $todo->id)
                    ->value('title');

                if (!empty($rawTitle)) {
                    $hasHash = !empty($todo->title_hash);
                    $isEncrypted = $this->isEncrypted($rawTitle);

                    if (!$hasHash || !$isEncrypted) {
                        // Plain-Text merken für späteres Setzen
                        $plainTitle = $rawTitle;
                        $needsUpdate = true;
                    }
                }

                if ($needsUpdate) {
                    if (!$isDryRun) {
                        // Verschlüsselung direkt über DB durchführen
                        $updates = [];
                        $teamSalt = null; // CheckinTodos haben kein team_id, daher null
                        
                        if ($plainTitle !== null) {
                            $encryptedTitle = Crypt::encryptString($plainTitle);
                            $updates['title'] = $encryptedTitle;
                            $updates['title_hash'] = FieldHasher::hmacSha256($plainTitle, $teamSalt);
                        }
                        
                        if (!empty($updates)) {
                            $updates['updated_at'] = now();
                            DB::table('checkin_todos')
                                ->where('id', $todo->id)
                                ->update($updates);
                        }
                    }
                    $encrypted++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("  ❌ Fehler bei Todo #{$todo->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($isDryRun) {
            $this->info("🔍 DRY-RUN: {$encrypted} Todo(s) würden verschlüsselt werden.");
            $this->info("   {$skipped} Todo(s) bereits verschlüsselt oder leer.");
        } else {
            $this->info("✅ {$encrypted} Todo(s) erfolgreich verschlüsselt.");
            $this->info("   {$skipped} Todo(s) bereits verschlüsselt oder leer.");
            if ($errors > 0) {
                $this->warn("   ⚠️  {$errors} Fehler aufgetreten.");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Prüft ob ein Wert bereits verschlüsselt ist
     * Verschlüsselte Werte sind base64-kodiert und haben eine bestimmte Länge/Struktur
     */
    private function isEncrypted(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Laravel Crypt erzeugt base64-kodierte Strings
        // Verschlüsselte Werte sind typischerweise länger und haben base64-Format
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        // Verschlüsselte Werte haben typischerweise eine Mindestlänge
        // und enthalten nicht-printable Zeichen nach Decodierung
        return strlen($decoded) > 16 && !ctype_print($decoded);
    }
}

