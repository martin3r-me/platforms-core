<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolDiscoveryService;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ToolRequest;
use Illuminate\Support\Facades\Log;

/**
 * Tool zum Anmelden von fehlenden Tools/Funktionen
 * 
 * Ermöglicht es dem LLM, Bedarf an Tools/Funktionen anzumelden, die es benötigt,
 * um eine Aufgabe qualifiziert zu erledigen, aber die aktuell nicht verfügbar sind.
 * 
 * Das System kann dann:
 * - Ähnliche Tools vorschlagen
 * - Feedback für Entwickler sammeln
 * - Optional: Neue Tools dynamisch erstellen (future)
 */
class RequestToolTool implements ToolContract
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolDiscoveryService $discovery
    ) {}

    public function getName(): string
    {
        return 'tools.request';
    }

    public function getDescription(): string
    {
        return 'Melde einen Bedarf an Tools oder Funktionen an, die dir fehlen, um eine Aufgabe qualifiziert zu erledigen. Nutze dieses Tool, wenn du erkannt hast, dass du eine Aufgabe NICHT lösen kannst, weil dir ein benötigtes Tool fehlt. WICHTIG: Nutze dieses Tool NUR, wenn du sicher bist, dass du die Aufgabe nicht mit den verfügbaren Tools lösen kannst. Kommuniziere dem Nutzer vorher klar, dass du die Aufgabe nicht ausführen kannst, und nutze dann dieses Tool, um den Bedarf anzumelden. Das System wird dann ähnliche Tools vorschlagen oder den Bedarf für Entwickler dokumentieren.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'description' => [
                    'type' => 'string',
                    'description' => 'Beschreibe die Funktionalität, die du benötigst (z.B. "Ich bräuchte ein Tool, das Projekte nach Status filtert" oder "Ich brauche eine Funktion, um OKR-Key-Results zu archivieren")',
                ],
                'use_case' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibe den konkreten Use-Case oder die Aufgabe, für die du dieses Tool benötigst',
                ],
                'suggested_name' => [
                    'type' => 'string',
                    'description' => 'Optional: Vorschlag für den Tool-Namen (z.B. "planner.projects.filter_by_status" oder "okrs.key_results.archive")',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Optional: Kategorie des benötigten Tools (z.B. "query" für Lese-Tool, "action" für Schreib-Tool, "utility" für Hilfs-Tool)',
                    'enum' => ['query', 'action', 'utility'],
                ],
                'module' => [
                    'type' => 'string',
                    'description' => 'Optional: In welchem Modul sollte dieses Tool sein? (z.B. "planner", "okrs", "core")',
                ],
            ],
            'required' => ['description'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $description = $arguments['description'] ?? '';
            $useCase = $arguments['use_case'] ?? null;
            $suggestedName = $arguments['suggested_name'] ?? null;
            $category = $arguments['category'] ?? null;
            $module = $arguments['module'] ?? null;
            
            if (empty($description)) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    'Beschreibung der benötigten Funktionalität ist erforderlich'
                );
            }
            
            // 1. Generiere Deduplication-Key
            $dedupKey = $this->generateDedupKey($description, $useCase, $suggestedName, $module);
            
            // 2. Prüfe auf ähnliche Requests (Deduping)
            $similarRequests = $this->findSimilarRequests($dedupKey, $description, $useCase);
            
            // 3. Suche nach ähnlichen Tools
            $similarTools = $this->findSimilarTools($description, $module);
            
            // 4. Speichere den Bedarf in der Datenbank (für Entwickler)
            $requestId = $this->logToolRequest([
                'description' => $description,
                'use_case' => $useCase,
                'suggested_name' => $suggestedName,
                'category' => $category,
                'module' => $module,
                'user_id' => $context->user?->id,
                'team_id' => $context->team?->id,
                'similar_tools' => $similarTools,
                'similar_requests' => $similarRequests,
                'deduplication_key' => $dedupKey,
                'timestamp' => now()->toIso8601String(),
            ]);
            
            // 3. Erstelle Response
            $result = [
                'request_received' => true,
                'request_id' => $requestId, // WICHTIG: ID für Tracking
                'message' => 'Dein Bedarf wurde registriert. Hier sind ähnliche Tools, die dir vielleicht helfen:',
                'similar_tools' => $similarTools,
                'suggestion' => $this->generateSuggestion($description, $similarTools, $suggestedName),
            ];
            
            // Wenn ähnliche Tools gefunden wurden
            if (count($similarTools) > 0) {
                $result['message'] = 'Ich habe ähnliche Tools gefunden, die dir vielleicht helfen können:';
            } else {
                $result['message'] = 'Dein Bedarf wurde registriert. Leider habe ich keine ähnlichen Tools gefunden. Der Bedarf wurde für die Entwickler dokumentiert.';
            }
            
            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error(
                'EXECUTION_ERROR',
                'Fehler beim Anmelden des Tool-Bedarfs: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Findet ähnliche Tools basierend auf der Beschreibung
     */
    private function findSimilarTools(string $description, ?string $module = null): array
    {
        $allTools = $this->registry->all();
        $similarTools = [];
        
        // Extrahiere Keywords aus der Beschreibung
        $keywords = $this->extractKeywords($description);
        
        foreach ($allTools as $tool) {
            $toolName = strtolower($tool->getName());
            $toolDescription = strtolower($tool->getDescription());
            
            // Filter nach Modul, falls angegeben
            if ($module && !str_starts_with($toolName, $module . '.')) {
                continue;
            }
            
            // Prüfe Ähnlichkeit
            $score = 0;
            foreach ($keywords as $keyword) {
                if (stripos($toolName, $keyword) !== false) {
                    $score += 10;
                }
                if (stripos($toolDescription, $keyword) !== false) {
                    $score += 5;
                }
            }
            
            if ($score > 0) {
                $metadata = $this->discovery->getToolMetadata($tool);
                $similarTools[] = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'relevance_score' => $score,
                    'module' => $this->extractModuleFromToolName($tool->getName()),
                    'category' => $metadata['category'] ?? null,
                ];
            }
        }
        
        // Sortiere nach Relevanz
        usort($similarTools, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);
        
        // Maximal 5 ähnliche Tools zurückgeben
        return array_slice($similarTools, 0, 5);
    }
    
    /**
     * Generiert eine hilfreiche Suggestion
     */
    private function generateSuggestion(string $description, array $similarTools, ?string $suggestedName): string
    {
        if (count($similarTools) > 0) {
            $topTool = $similarTools[0];
            return "Das Tool '{$topTool['name']}' könnte dir helfen. " .
                   "Falls das nicht passt, wurde dein Bedarf für die Entwickler dokumentiert.";
        }
        
        if ($suggestedName) {
            return "Dein Vorschlag '{$suggestedName}' wurde dokumentiert. " .
                   "Die Entwickler werden diesen Bedarf prüfen.";
        }
        
        return "Dein Bedarf wurde dokumentiert. Die Entwickler werden prüfen, ob ein entsprechendes Tool erstellt werden kann.";
    }
    
    /**
     * Generiert einen Deduplication-Key für einen Tool-Request
     */
    private function generateDedupKey(string $description, ?string $useCase, ?string $suggestedName, ?string $module): string
    {
        // Normalisiere Text (lowercase, entferne Sonderzeichen)
        $normalizedDescription = mb_strtolower(trim($description));
        $normalizedUseCase = $useCase ? mb_strtolower(trim($useCase)) : '';
        $normalizedName = $suggestedName ? mb_strtolower(trim($suggestedName)) : '';
        $normalizedModule = $module ? mb_strtolower(trim($module)) : '';
        
        // Erstelle Key aus normalisierten Daten
        $keyData = [
            'description' => $normalizedDescription,
            'use_case' => $normalizedUseCase,
            'suggested_name' => $normalizedName,
            'module' => $normalizedModule,
        ];
        
        return hash('sha256', json_encode($keyData));
    }
    
    /**
     * Findet ähnliche Requests (für Deduping)
     * 
     * @return array IDs ähnlicher Requests
     */
    private function findSimilarRequests(string $dedupKey, string $description, ?string $useCase): array
    {
        try {
            // 1. Prüfe exakten Dedup-Key
            $exactMatch = ToolRequest::where('deduplication_key', $dedupKey)
                ->where('status', '!=', ToolRequest::STATUS_REJECTED) // Ignoriere abgelehnte
                ->first();
            
            if ($exactMatch) {
                return [$exactMatch->id];
            }
            
            // 2. Prüfe ähnliche Beschreibungen (fuzzy matching)
            $similar = ToolRequest::where('status', '!=', ToolRequest::STATUS_REJECTED)
                ->where(function($query) use ($description, $useCase) {
                    // Ähnliche Beschreibung (LIKE mit Keywords)
                    $keywords = $this->extractKeywords($description);
                    foreach ($keywords as $keyword) {
                        if (mb_strlen($keyword) > 3) {
                            $query->orWhere('description', 'like', "%{$keyword}%");
                        }
                    }
                    
                    // Ähnlicher Use-Case
                    if ($useCase) {
                        $useCaseKeywords = $this->extractKeywords($useCase);
                        foreach ($useCaseKeywords as $keyword) {
                            if (mb_strlen($keyword) > 3) {
                                $query->orWhere('use_case', 'like', "%{$keyword}%");
                            }
                        }
                    }
                })
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
            
            return $similar->pluck('id')->toArray();
        } catch (\Throwable $e) {
            Log::warning('[Tool Request] Fehler beim Finden ähnlicher Requests', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Speichert den Tool-Bedarf in der Datenbank (für Entwickler)
     * 
     * @return int|null Die ID des erstellten Requests
     */
    private function logToolRequest(array $data): ?int
    {
        try {
            // Prüfe ob bereits ähnlicher Request existiert (Deduping)
            $dedupKey = $data['deduplication_key'] ?? null;
            $similarRequests = $data['similar_requests'] ?? [];
            
            if ($dedupKey && !empty($similarRequests)) {
                // Ähnliche Requests gefunden - logge für Entwickler
                Log::info('[Tool Request] Ähnliche Requests gefunden', [
                    'similar_request_ids' => $similarRequests,
                    'description' => $data['description'],
                ]);
            }
            
            // Speichere in Datenbank
            $request = ToolRequest::create([
                'user_id' => $data['user_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'description' => $data['description'],
                'use_case' => $data['use_case'] ?? null,
                'suggested_name' => $data['suggested_name'] ?? null,
                'category' => $data['category'] ?? null,
                'module' => $data['module'] ?? null,
                'status' => ToolRequest::STATUS_PENDING,
                'similar_tools' => $data['similar_tools'] ?? null,
                'similar_requests' => $similarRequests,
                'deduplication_key' => $dedupKey,
                'metadata' => [
                    'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
                ],
            ]);
            
            // Logge auch für Entwickler
            Log::info('[Tool Request] Neuer Bedarf in Datenbank gespeichert', [
                'request_id' => $request->id,
                'description' => $data['description'],
                'module' => $data['module'] ?? 'unknown',
                'similar_requests_count' => count($similarRequests),
            ]);
            
            return $request->id; // WICHTIG: ID zurückgeben
        } catch (\Throwable $e) {
            // Fallback: Nur Logging, wenn DB-Speicherung fehlschlägt
            Log::warning('[Tool Request] Fehler beim Speichern in Datenbank', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            
            return null; // Keine ID bei Fehler
        }
    }
    
    /**
     * Extrahiert Keywords aus einer Beschreibung
     */
    private function extractKeywords(string $text): array
    {
        $stopWords = ['ein', 'eine', 'der', 'die', 'das', 'und', 'oder', 'mit', 'für', 'von', 'zu', 'auf', 'in', 'an'];
        $words = preg_split('/\s+/u', strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
        
        return array_filter($words, function($word) use ($stopWords) {
            return mb_strlen($word, 'UTF-8') > 2 && !in_array($word, $stopWords);
        });
    }
    
    /**
     * Extrahiert Modul aus Tool-Namen
     */
    private function extractModuleFromToolName(string $toolName): string
    {
        if (str_contains($toolName, '.')) {
            return explode('.', $toolName)[0];
        }
        return 'core';
    }
}

