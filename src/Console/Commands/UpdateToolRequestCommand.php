<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\ToolRequest;

class UpdateToolRequestCommand extends Command
{
    protected $signature = 'core:tool-request-update 
                            {id : ID des Tool-Requests}
                            {--status= : Neuer Status (pending, in_progress, completed, rejected)}
                            {--notes= : Entwickler-Notizen}
                            {--assign= : User-ID, dem der Request zugewiesen werden soll}';

    protected $description = 'Aktualisiert einen Tool-Request (Status, Notizen, Zuweisung)';

    public function handle()
    {
        $id = $this->argument('id');
        
        try {
            $request = ToolRequest::findOrFail($id);
            
            $this->info("Tool-Request #{$id}:");
            $this->line("  Beschreibung: " . $request->description);
            $this->line("  Aktueller Status: {$request->status}");
            $this->newLine();
            
            $updated = false;
            
            // Status aktualisieren
            if ($status = $this->option('status')) {
                if (!in_array($status, [
                    ToolRequest::STATUS_PENDING,
                    ToolRequest::STATUS_IN_PROGRESS,
                    ToolRequest::STATUS_COMPLETED,
                    ToolRequest::STATUS_REJECTED,
                ])) {
                    $this->error("❌ Ungültiger Status: {$status}");
                    $this->line("Erlaubte Status: pending, in_progress, completed, rejected");
                    return 1;
                }
                
                $request->status = $status;
                $updated = true;
                $this->line("✅ Status aktualisiert: {$status}");
            }
            
            // Notizen aktualisieren
            if ($notes = $this->option('notes')) {
                $request->developer_notes = $notes;
                $updated = true;
                $this->line("✅ Notizen aktualisiert");
            }
            
            // Zuweisung aktualisieren
            if ($assignUserId = $this->option('assign')) {
                $request->assigned_to_user_id = $assignUserId;
                $updated = true;
                $this->line("✅ Zugewiesen an User-ID: {$assignUserId}");
            }
            
            if ($updated) {
                $request->save();
                $this->newLine();
                $this->info("✅ Tool-Request erfolgreich aktualisiert!");
            } else {
                $this->warn("⚠️  Keine Änderungen vorgenommen. Verwende --status, --notes oder --assign");
            }
            
            return 0;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("❌ Tool-Request #{$id} nicht gefunden!");
            return 1;
        } catch (\Throwable $e) {
            $this->error("❌ Fehler: " . $e->getMessage());
            return 1;
        }
    }
}

