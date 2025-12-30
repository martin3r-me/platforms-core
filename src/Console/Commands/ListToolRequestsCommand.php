<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\ToolRequest;

class ListToolRequestsCommand extends Command
{
    protected $signature = 'core:tool-requests 
                            {--status= : Filter nach Status (pending, in_progress, completed, rejected)}
                            {--module= : Filter nach Modul}
                            {--limit=20 : Anzahl der Requests}';

    protected $description = 'Listet alle Tool-Requests auf, die vom LLM angemeldet wurden';

    public function handle()
    {
        $this->info("=== Tool Requests ===");
        $this->newLine();
        
        try {
            $query = ToolRequest::query()->orderBy('created_at', 'desc');
            
            // Filter nach Status
            if ($status = $this->option('status')) {
                $query->where('status', $status);
            }
            
            // Filter nach Modul
            if ($module = $this->option('module')) {
                $query->where('module', $module);
            }
            
            // Limit
            $limit = (int) $this->option('limit');
            $requests = $query->limit($limit)->get();
            
            if ($requests->isEmpty()) {
                $this->warn("⚠️  Keine Tool-Requests gefunden!");
                return 0;
            }
            
            $this->line("✅ " . $requests->count() . " Tool-Request(s) gefunden:");
            $this->newLine();
            
            foreach ($requests as $request) {
                $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->line("ID: {$request->id}");
                $this->line("Status: " . $this->formatStatus($request->status));
                $this->line("Erstellt: " . $request->created_at->format('Y-m-d H:i:s'));
                
                if ($request->module) {
                    $this->line("Modul: {$request->module}");
                }
                
                if ($request->category) {
                    $this->line("Kategorie: {$request->category}");
                }
                
                if ($request->suggested_name) {
                    $this->line("Vorgeschlagener Name: {$request->suggested_name}");
                }
                
                $this->line("Beschreibung: " . wordwrap($request->description, 80));
                
                if ($request->use_case) {
                    $this->line("Use-Case: " . wordwrap($request->use_case, 80));
                }
                
                if ($request->user) {
                    $this->line("User: {$request->user->name} ({$request->user->email})");
                }
                
                if ($request->team) {
                    $this->line("Team: {$request->team->name}");
                }
                
                if ($request->assignedTo) {
                    $this->line("Zugewiesen an: {$request->assignedTo->name}");
                }
                
                if ($request->developer_notes) {
                    $this->line("Entwickler-Notizen: " . wordwrap($request->developer_notes, 80));
                }
                
                if ($request->similar_tools && count($request->similar_tools) > 0) {
                    $this->line("Ähnliche Tools gefunden: " . count($request->similar_tools));
                    foreach ($request->similar_tools as $tool) {
                        $this->line("  - {$tool['name']} (Score: {$tool['relevance_score']})");
                    }
                }
                
                $this->newLine();
            }
            
            // Statistik
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("Statistik:");
            $this->line("  Pending: " . ToolRequest::pending()->count());
            $this->line("  In Progress: " . ToolRequest::inProgress()->count());
            $this->line("  Completed: " . ToolRequest::completed()->count());
            $this->line("  Rejected: " . ToolRequest::where('status', ToolRequest::STATUS_REJECTED)->count());
            $this->line("  Gesamt: " . ToolRequest::count());
            
            return 0;
        } catch (\Throwable $e) {
            $this->error("❌ Fehler: " . $e->getMessage());
            return 1;
        }
    }
    
    private function formatStatus(string $status): string
    {
        return match($status) {
            ToolRequest::STATUS_PENDING => "⏳ Pending",
            ToolRequest::STATUS_IN_PROGRESS => "🔄 In Progress",
            ToolRequest::STATUS_COMPLETED => "✅ Completed",
            ToolRequest::STATUS_REJECTED => "❌ Rejected",
            default => $status,
        };
    }
}

