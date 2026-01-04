<?php

namespace Platform\Core\Services;

use Illuminate\Support\Str;

/**
 * Service für Intention-Verifikation
 * 
 * Prüft, ob die Tool-Results die ursprüngliche User-Intention erfüllen
 */
class IntentionVerificationService
{
    protected ?OpenAiService $openAiService = null;

    public function __construct()
    {
        // Lazy-Loading: OpenAiService nur wenn nötig
        try {
            $this->openAiService = app(OpenAiService::class);
        } catch (\Throwable $e) {
            // Service nicht verfügbar - Pattern-Matching funktioniert trotzdem
        }
    }

    /**
     * Prüft, ob die Tool-Results die ursprüngliche Intention erfüllen
     * 
     * @param string $originalIntent Die ursprüngliche User-Anfrage
     * @param array $toolResults Alle Tool-Results dieser Runde
     * @param array $actionSummary Die Action Summary (was wurde geändert)
     * @return VerificationResult
     */
    public function verify(string $originalIntent, array $toolResults, array $actionSummary = []): VerificationResult
    {
        // 1. Extrahiere Intention aus User-Request
        $intention = $this->extractIntention($originalIntent);
        
        // 2. Prüfe Vollständigkeit basierend auf Action Summary und Tool-Results (systematisch)
        $completeness = $this->checkCompleteness($intention, $actionSummary, $toolResults);
        
        // 3. Prüfe Konsistenz (z.B. "Ich habe X erstellt" vs. tatsächlich erstellt)
        $consistency = $this->checkConsistency($intention, $toolResults, $actionSummary);
        
        // 4. Wenn Probleme gefunden: Erstelle Verifikations-Result
        if (!$completeness->isComplete() || !$consistency->isConsistent()) {
            return VerificationResult::withIssues($completeness, $consistency, $intention);
        }
        
        return VerificationResult::ok();
    }

    /**
     * Bestimmt (heuristisch) das erwartete Tool für die ursprüngliche User-Anfrage.
     *
     * Zweck: Der Controller kann damit im laufenden Run "auto-injecten", wenn das erwartete Tool
     * noch nicht verfügbar ist (z.B. nur Discovery-Tools aktiv).
     *
     * IMPORTANT: Loose-Pattern bleibt erhalten – das ist nur ein Hinweis/Helper für Robustheit.
     */
    public function expectedToolFor(string $originalIntent): ?string
    {
        $intention = $this->extractIntention($originalIntent);

        if ($intention->isEmpty() || !$intention->type) {
            return null;
        }

        if ($intention->type === 'read' && $intention->target) {
            return $this->getExpectedToolForRead(Str::lower($intention->target));
        }

        if (in_array($intention->type, ['create', 'update', 'delete'], true) && $intention->target) {
            return $this->getExpectedToolForWrite(Str::lower($intention->target), $intention->type);
        }

        return null;
    }

    /**
     * Bestimmt das erwartete Tool für WRITE-Operationen (create/update/delete).
     *
     * IMPORTANT: Loose – es ist nur ein Helper für Auto-Injection/Guardrails,
     * kein harter Enforcer.
     */
    protected function getExpectedToolForWrite(string $target, string $type): ?string
    {
        $target = Str::lower(trim($target));

        $suffix = match ($type) {
            'create' => 'POST',
            'update' => 'PUT',
            'delete' => 'DELETE',
            default => null,
        };
        if ($suffix === null) { return null; }

        // Grobes Entity-Mapping (de/en)
        $entityMap = [
            // planner
            'aufgabe' => 'planner.tasks',
            'aufgaben' => 'planner.tasks',
            'task' => 'planner.tasks',
            'tasks' => 'planner.tasks',
            'slot' => 'planner.project_slots',
            'slots' => 'planner.project_slots',
            'projekt' => 'planner.projects',
            'projekte' => 'planner.projects',
            'project' => 'planner.projects',
            'projects' => 'planner.projects',
            // crm
            'company' => 'crm.companies',
            'companies' => 'crm.companies',
            'unternehmen' => 'crm.companies',
            'firma' => 'crm.companies',
            'contact' => 'crm.contacts',
            'contacts' => 'crm.contacts',
            'kontakt' => 'crm.contacts',
            'kontakte' => 'crm.contacts',
        ];

        foreach ($entityMap as $needle => $base) {
            if ($target === $needle || str_contains($target, $needle)) {
                return $base . '.' . $suffix;
            }
        }

        return null;
    }

    /**
     * Extrahiert die Intention aus der User-Nachricht
     */
    protected function extractIntention(string $userMessage): Intention
    {
        $message = Str::lower(trim($userMessage));
        
        // Pattern-Matching für häufige Fälle
        $intention = $this->extractWithPatterns($message);
        
        // Wenn Pattern-Matching nichts gefunden hat und LLM verfügbar und aktiviert: Nutze LLM
        $useLLMExtraction = config('tools.intention_verification.use_llm_extraction', true);
        if ($intention->isEmpty() && $this->openAiService && $useLLMExtraction) {
            $intention = $this->extractWithLLM($userMessage);
        }
        
        return $intention;
    }

    /**
     * Extrahiert Intention mit Pattern-Matching (schnell, effizient)
     */
    protected function extractWithPatterns(string $message): Intention
    {
        $intention = new Intention();
        
        // DELETE-Patterns - zuerst "alle" prüfen
        if (preg_match('/lösche?\s+alle\s+(.+?)(?:\s+aus|\s+im|\s+von|$)/i', $message, $allMatches)) {
            $intention->type = 'delete';
            $intention->target = trim($allMatches[1] ?? '');
            $intention->expectedCount = null; // "alle" = unbekannte Anzahl
            $intention->isAll = true;
        } elseif (preg_match('/lösche?\s+(\d+)\s+(.+?)(?:\s+aus|\s+im|\s+von|$)/i', $message, $matches)) {
            $intention->type = 'delete';
            $intention->target = trim($matches[2] ?? '');
            $intention->expectedCount = (int)$matches[1];
        } elseif (preg_match('/lösche?\s+(.+?)(?:\s+aus|\s+im|\s+von|$)/i', $message, $matches)) {
            $intention->type = 'delete';
            $intention->target = trim($matches[1] ?? '');
            $intention->expectedCount = 1; // Einzelnes Löschen
        } elseif (preg_match('/entferne?\s+alle\s+(.+?)(?:\s+aus|\s+im|\s+von|$)/i', $message, $allMatches)) {
            $intention->type = 'delete';
            $intention->target = trim($allMatches[1] ?? '');
            $intention->expectedCount = null;
            $intention->isAll = true;
        } elseif (preg_match('/entferne?\s+(\d+)\s+(.+?)(?:\s+aus|\s+im|\s+von|$)/i', $message, $matches)) {
            $intention->type = 'delete';
            $intention->target = trim($matches[2] ?? '');
            $intention->expectedCount = (int)$matches[1];
        } elseif (preg_match('/entferne?\s+(.+?)(?:\s+aus|\s+im|\s+von|$)/i', $message, $matches)) {
            $intention->type = 'delete';
            $intention->target = trim($matches[1] ?? '');
            $intention->expectedCount = 1;
        }
        
        // CREATE-Patterns
        if (preg_match('/erstelle?\s+(?:ein\s+)?(?:neues?\s+)?(.+?)(?:\s+mit|\s+für|\s+in|$)/i', $message, $matches)) {
            $intention->type = 'create';
            $intention->target = $matches[1] ?? null;
            $intention->expectedCount = 1;
        } elseif (preg_match('/erzeuge?\s+(?:ein\s+)?(?:neues?\s+)?(.+?)(?:\s+mit|\s+für|\s+in|$)/i', $message, $matches)) {
            $intention->type = 'create';
            $intention->target = $matches[1] ?? null;
            $intention->expectedCount = 1;
        }
        
        // UPDATE-Patterns
        if (preg_match('/(?:ändere?|bearbeite?|aktualisiere?)\s+(?:das\s+)?(.+?)(?:\s+mit|\s+zu|\s+auf|$)/i', $message, $matches)) {
            $intention->type = 'update';
            $intention->target = $matches[1] ?? null;
            $intention->expectedCount = 1;
        }
        
        // LIST/GET-Patterns - extrahiere Target für Tool-Verifikation
        if (preg_match('/(?:zeige?|liste?|hole?|get|anzeige?)\s+(?:alle\s+)?(.+?)(?:\s+aus|\s+im|\s+von|\s+des|\s+der|\s+dem|$)/i', $message, $matches)) {
            $intention->type = 'read';
            $intention->target = trim($matches[1] ?? '');
            // Read-Operationen brauchen Tool-Verifikation (wurde das richtige Tool aufgerufen?)
        }
        
        return $intention;
    }

    /**
     * Extrahiert Intention mit LLM (für komplexe Fälle)
     */
    protected function extractWithLLM(string $userMessage): Intention
    {
        if (!$this->openAiService) {
            return new Intention();
        }
        
        try {
            $prompt = "Analysiere die folgende User-Anfrage und extrahiere die Intention:\n\n";
            $prompt .= "Anfrage: {$userMessage}\n\n";
            $prompt .= "Antworte NUR mit JSON im Format:\n";
            $prompt .= '{"type": "delete|create|update|read", "target": "was soll geändert werden", "expectedCount": 3, "isAll": false}';
            
            $response = $this->openAiService->chat([
                ['role' => 'system', 'content' => 'Du bist ein Intention-Extraktor. Antworte NUR mit JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ], 'gpt-4o-mini', ['max_tokens' => 200]);
            
            $content = $response['content'] ?? '{}';
            // Entferne Markdown-Code-Blöcke falls vorhanden
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $content = trim($content);
            
            $data = json_decode($content, true);
            if ($data) {
                $intention = new Intention();
                $intention->type = $data['type'] ?? null;
                $intention->target = $data['target'] ?? null;
                $intention->expectedCount = $data['expectedCount'] ?? null;
                $intention->isAll = $data['isAll'] ?? false;
                return $intention;
            }
        } catch (\Throwable $e) {
            // Fehler bei LLM-Extraktion - nutze leere Intention
            \Log::debug('[IntentionVerification] LLM-Extraktion fehlgeschlagen', [
                'error' => $e->getMessage()
            ]);
        }
        
        return new Intention();
    }

    /**
     * Prüft Vollständigkeit basierend auf Action Summary und Tool-Results
     */
    protected function checkCompleteness(Intention $intention, array $actionSummary, array $toolResults = []): CompletenessCheck
    {
        if ($intention->isEmpty()) {
            return CompletenessCheck::complete();
        }
        
        // READ-Operationen: Prüfe ob das richtige Tool aufgerufen wurde
        if ($intention->type === 'read' && $intention->target) {
            // Diese Prüfung wird in checkConsistency gemacht (welche Tools wurden aufgerufen)
            return CompletenessCheck::complete();
        }
        
        $modelsCreated = $actionSummary['models_created'] ?? 0;
        $modelsUpdated = $actionSummary['models_updated'] ?? 0;
        $modelsDeleted = $actionSummary['models_deleted'] ?? 0;
        
        // DELETE-Check: Systematisch Tool-Results analysieren für automatisch gelöschte Entitäten
        if ($intention->type === 'delete') {
            // Analysiere Tool-Results systematisch & generisch: Erkenne automatisch gelöschte Entitäten
            // Tools kommunizieren ihre Cascade-Löschungen selbst (z.B. deleted_*_count, cascade_deleted)
            $actualDeleted = $this->analyzeActualDeletions($intention, $toolResults, $modelsDeleted);
            
            // Generisch: Normalisiere Target (entferne spezifische Kontexte wie "im aktuellen Team")
            $normalizedTarget = $this->normalizeTarget($intention->target);
            
            if ($intention->isAll) {
                // "Lösche alle X" - prüfe ob überhaupt etwas gelöscht wurde
                if ($actualDeleted['total'] === 0) {
                    return CompletenessCheck::incomplete(
                        "Es sollten alle '{$normalizedTarget}' gelöscht werden, aber es wurde nichts gelöscht."
                    );
                }
            } elseif ($intention->expectedCount !== null) {
                // "Lösche 3 X" - prüfe ob genug gelöscht wurden
                if ($actualDeleted['total'] < $intention->expectedCount) {
                    return CompletenessCheck::incomplete(
                        "Es sollten {$intention->expectedCount} '{$normalizedTarget}' gelöscht werden, aber nur {$actualDeleted['total']} wurden gelöscht."
                    );
                }
            } else {
                // Einzelnes Löschen ohne explizite Anzahl - prüfe ob überhaupt etwas gelöscht wurde
                if ($actualDeleted['total'] === 0) {
                    return CompletenessCheck::incomplete(
                        "Es sollten '{$normalizedTarget}' gelöscht werden, aber es wurde nichts gelöscht."
                    );
                }
            }
        }
        
        // CREATE-Check
        if ($intention->type === 'create') {
            if ($intention->expectedCount !== null && $modelsCreated < $intention->expectedCount) {
                return CompletenessCheck::incomplete(
                    "Es sollten {$intention->expectedCount} '{$intention->target}' erstellt werden, aber nur {$modelsCreated} wurden erstellt."
                );
            }
        }
        
        // UPDATE-Check
        if ($intention->type === 'update') {
            if ($intention->expectedCount !== null && $modelsUpdated < $intention->expectedCount) {
                return CompletenessCheck::incomplete(
                    "Es sollten {$intention->expectedCount} '{$intention->target}' aktualisiert werden, aber nur {$modelsUpdated} wurden aktualisiert."
                );
            }
        }
        
        return CompletenessCheck::complete();
    }
    
    /**
     * Analysiert systematisch die Tool-Results, um tatsächlich gelöschte Entitäten zu erkennen
     * Berücksichtigt automatisch gelöschte abhängige Entitäten, die von Tools kommuniziert werden
     * 
     * Loose & Systematisch: Generisch - keine hardcodierten Tool-/Modul-Namen
     * Tools kommunizieren ihre Cascade-Löschungen selbst (z.B. deleted_*_count, cascade_deleted, etc.)
     */
    protected function analyzeActualDeletions(Intention $intention, array $toolResults, int $modelsDeletedFromSummary): array
    {
        $target = Str::lower(trim($intention->target ?? ''));
        $actualDeleted = [
            'total' => $modelsDeletedFromSummary,
            'cascade_deletions' => [], // Generisch: Alle automatisch gelöschten Entitäten
        ];
        
        // Systematisch durch Tool-Results: Generisch nach Cascade-Löschungen suchen
        // Tools kommunizieren ihre Cascade-Löschungen selbst (z.B. deleted_*_count, cascade_deleted, etc.)
        foreach ($toolResults as $result) {
            if (!($result['success'] ?? false)) {
                continue;
            }
            
            $data = $result['data'] ?? [];
            if (!is_array($data)) {
                continue;
            }
            
            // Generisch: Suche nach Patterns für automatisch gelöschte Entitäten
            // Tools können verschiedene Patterns verwenden:
            // - deleted_*_count (z.B. deleted_tasks_count, deleted_slots_count)
            // - cascade_deleted (Array mit gelöschten Entitäten)
            // - deleted_dependencies (Array)
            
            $cascadeDeleted = [];
            
            // Pattern 1: deleted_*_count (z.B. deleted_tasks_count, deleted_slots_count)
            foreach ($data as $key => $value) {
                if (preg_match('/^deleted_(.+?)_count$/', $key, $matches)) {
                    $entityType = $matches[1] ?? '';
                    $count = (int)$value;
                    if ($count > 0) {
                        $cascadeDeleted[$entityType] = ($cascadeDeleted[$entityType] ?? 0) + $count;
                    }
                }
            }
            
            // Pattern 2: cascade_deleted (Array)
            if (isset($data['cascade_deleted']) && is_array($data['cascade_deleted'])) {
                foreach ($data['cascade_deleted'] as $entityType => $count) {
                    $cascadeDeleted[$entityType] = ($cascadeDeleted[$entityType] ?? 0) + (int)$count;
                }
            }
            
            // Pattern 3: deleted_dependencies (Array)
            if (isset($data['deleted_dependencies']) && is_array($data['deleted_dependencies'])) {
                foreach ($data['deleted_dependencies'] as $entityType => $count) {
                    $cascadeDeleted[$entityType] = ($cascadeDeleted[$entityType] ?? 0) + (int)$count;
                }
            }
            
            // Speichere Cascade-Löschungen generisch
            if (!empty($cascadeDeleted)) {
                foreach ($cascadeDeleted as $entityType => $count) {
                    $actualDeleted['cascade_deletions'][$entityType] = 
                        ($actualDeleted['cascade_deletions'][$entityType] ?? 0) + $count;
                }
            }
        }
        
        // Systematisch: Berechne total basierend auf tatsächlich gelöschten Entitäten
        // Loose: Berücksichtige automatisch gelöschte Entitäten, wenn User diese löschen wollte
        // Generisch: Prüfe ob User-Intention zu automatisch gelöschten Entitäten passt
        if (!empty($actualDeleted['cascade_deletions'])) {
            foreach ($actualDeleted['cascade_deletions'] as $entityType => $count) {
                // Generisch: Prüfe ob User diese Entität löschen wollte (loose Pattern-Matching)
                if ($this->targetMatchesEntity($target, $entityType)) {
                    $actualDeleted['total'] += $count;
                }
            }
        }
        
        return $actualDeleted;
    }
    
    /**
     * Prüft generisch, ob User-Intention zu einer Entität passt (loose Pattern-Matching)
     * Keine hardcodierten Entitäts-Namen - generisch für alle Module/Tools
     */
    protected function targetMatchesEntity(string $target, string $entityType): bool
    {
        $target = Str::lower(trim($target));
        $entityType = Str::lower(trim($entityType));
        
        // Generisch: Prüfe ob Target die Entität enthält oder ähnlich ist
        // Loose: Unterstützt verschiedene Sprachen und Pluralformen
        return str_contains($target, $entityType) || 
               str_contains($entityType, $target) ||
               $this->isPluralOrSingular($target, $entityType);
    }
    
    /**
     * Prüft generisch, ob zwei Strings Plural/Singular-Varianten sind
     */
    protected function isPluralOrSingular(string $str1, string $str2): bool
    {
        // Einfache Heuristik: Prüfe ob ein String mit 's' endet und der andere nicht (oder umgekehrt)
        $str1Trimmed = rtrim($str1, 's');
        $str2Trimmed = rtrim($str2, 's');
        
        return $str1Trimmed === $str2Trimmed || $str2Trimmed === $str1Trimmed;
    }

    /**
     * Prüft Konsistenz zwischen Tool-Results und Action Summary
     */
    protected function checkConsistency(Intention $intention, array $toolResults, array $actionSummary): ConsistencyCheck
    {
        $issues = [];
        
        // Prüfe ob alle Tool-Calls erfolgreich waren
        // Systematisch & Generisch: Prüfe ob fehlgeschlagene Tools eigentlich nicht nötig waren
        // (z.B. Entität wurde bereits durch Cascade-Löschung gelöscht)
        $failedTools = [];
        $cascadeDeletions = []; // Generisch: Alle automatisch gelöschten Entitäten
        
        // Systematisch durch Tool-Results: Sammle alle Cascade-Löschungen generisch
        foreach ($toolResults as $result) {
            if (!($result['success'] ?? false)) {
                continue;
            }
            
            $data = $result['data'] ?? [];
            if (!is_array($data)) {
                continue;
            }
            
            // Generisch: Suche nach Cascade-Löschungen (wie in analyzeActualDeletions)
            foreach ($data as $key => $value) {
                if (preg_match('/^deleted_(.+?)_count$/', $key, $matches)) {
                    $entityType = $matches[1] ?? '';
                    $count = (int)$value;
                    if ($count > 0) {
                        $cascadeDeletions[$entityType] = ($cascadeDeletions[$entityType] ?? 0) + $count;
                    }
                }
            }
            
            if (isset($data['cascade_deleted']) && is_array($data['cascade_deleted'])) {
                foreach ($data['cascade_deleted'] as $entityType => $count) {
                    $cascadeDeletions[$entityType] = ($cascadeDeletions[$entityType] ?? 0) + (int)$count;
                }
            }
            
            if (isset($data['deleted_dependencies']) && is_array($data['deleted_dependencies'])) {
                foreach ($data['deleted_dependencies'] as $entityType => $count) {
                    $cascadeDeletions[$entityType] = ($cascadeDeletions[$entityType] ?? 0) + (int)$count;
                }
            }
        }
        
        // Prüfe fehlgeschlagene Tools systematisch & generisch
        foreach ($toolResults as $result) {
            if (!($result['success'] ?? false)) {
                $tool = $result['tool'] ?? 'Unbekannt';
                $error = $result['error'] ?? '';
                $errorCode = $result['error_code'] ?? '';
                
                // Generisch prüfen: War das fehlgeschlagene Tool eigentlich nicht nötig?
                // Prüfe ob Fehler darauf hindeutet, dass Entität bereits gelöscht wurde
                $shouldIgnore = false;
                
                // Pattern 1: Error-Codes die auf "bereits gelöscht" hindeuten
                $notFoundErrors = ['NOT_FOUND', 'ALREADY_DELETED', 'ENTITY_NOT_FOUND', 'RESOURCE_NOT_FOUND'];
                if (in_array($errorCode, $notFoundErrors, true)) {
                    // Generisch: Prüfe ob diese Entität durch Cascade-Löschung bereits gelöscht wurde
                    // Extrahiere Entitätstyp aus Tool-Namen (z.B. "planner.slots.DELETE" -> "slots")
                    $toolParts = explode('.', $tool);
                    if (count($toolParts) >= 2) {
                        $entityType = $toolParts[count($toolParts) - 2] ?? ''; // Vorletztes Element
                        $entityType = str_replace('_', '', $entityType); // Normalisiere (z.B. "project_slots" -> "projectslots")
                        
                        // Generisch: Prüfe ob diese Entität durch Cascade gelöscht wurde
                        foreach ($cascadeDeletions as $cascadeType => $count) {
                            $cascadeTypeNormalized = str_replace('_', '', Str::lower($cascadeType));
                            $entityTypeNormalized = Str::lower($entityType);
                            
                            if ($cascadeTypeNormalized === $entityTypeNormalized || 
                                str_contains($cascadeTypeNormalized, $entityTypeNormalized) ||
                                str_contains($entityTypeNormalized, $cascadeTypeNormalized)) {
                                // Entität wurde bereits durch Cascade gelöscht - kein echtes Problem
                                $shouldIgnore = true;
                                break;
                            }
                        }
                    }
                }
                
                // Pattern 2: Error-Message enthält Hinweise auf "bereits gelöscht"
                if (!$shouldIgnore && !empty($error)) {
                    $errorLower = Str::lower($error);
                    $alreadyDeletedPatterns = [
                        'bereits gelöscht',
                        'already deleted',
                        'nicht gefunden',
                        'not found',
                        'existiert nicht',
                        'does not exist',
                    ];
                    
                    foreach ($alreadyDeletedPatterns as $pattern) {
                        if (str_contains($errorLower, $pattern)) {
                            // Generisch: Prüfe ob durch Cascade gelöscht (wie oben)
                            $toolParts = explode('.', $tool);
                            if (count($toolParts) >= 2) {
                                $entityType = $toolParts[count($toolParts) - 2] ?? '';
                                $entityType = str_replace('_', '', $entityType);
                                
                                foreach ($cascadeDeletions as $cascadeType => $count) {
                                    $cascadeTypeNormalized = str_replace('_', '', Str::lower($cascadeType));
                                    $entityTypeNormalized = Str::lower($entityType);
                                    
                                    if ($cascadeTypeNormalized === $entityTypeNormalized || 
                                        str_contains($cascadeTypeNormalized, $entityTypeNormalized) ||
                                        str_contains($entityTypeNormalized, $cascadeTypeNormalized)) {
                                        $shouldIgnore = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (!$shouldIgnore) {
                    $failedTools[] = $tool;
                }
            }
        }
        
        if (!empty($failedTools)) {
            $issues[] = "Einige Tools sind fehlgeschlagen: " . implode(', ', $failedTools);
        }
        
        // READ-Operationen: Prüfe ob das richtige Tool aufgerufen wurde
        // WICHTIG: Nur warnen wenn:
        // 1. Das erwartete Tool noch nicht aufgerufen wurde
        // 2. UND es bereits mehrere Iterationen gibt (> 2)
        // 3. UND das falsche Tool mehrfach aufgerufen wurde (> 2 mal)
        if ($intention->type === 'read' && $intention->target) {
            $target = Str::lower($intention->target);
            $toolsCalled = array_map(function($result) {
                return $result['tool'] ?? '';
            }, $toolResults);
            
            // Mapping: User-Request → erwartetes Tool
            $expectedTool = $this->getExpectedToolForRead($target);
            
            if ($expectedTool && !in_array($expectedTool, $toolsCalled)) {
                // Zähle wie oft das falsche Tool aufgerufen wurde
                $toolCounts = array_count_values($toolsCalled);
                $totalCalls = count($toolsCalled);
                
                // Prüfe ob ein ähnliches Tool aufgerufen wurde (z.B. core.teams.GET statt planner.projects.GET)
                $similarTool = $this->findSimilarTool($expectedTool, $toolsCalled);
                
                // Preskriptive Konsistenz-Warnungen entfernt - LLM entscheidet selbst
                // Keine Anweisungen mehr, die die LLM in eine bestimmte Richtung lenken
            }
        }
        
        // Prüfe ob Tool-Results mit Action Summary übereinstimmen
        $toolsExecuted = count($toolResults);
        $expectedTools = $actionSummary['tools_executed'] ?? 0;
        
        if ($toolsExecuted !== $expectedTools && $expectedTools > 0) {
            $issues[] = "Anzahl ausgeführter Tools stimmt nicht überein: {$toolsExecuted} vs. {$expectedTools}";
        }
        
        if (empty($issues)) {
            return ConsistencyCheck::consistent();
        }
        
        return ConsistencyCheck::inconsistent($issues);
    }
    
    /**
     * Bestimmt das erwartete Tool für eine READ-Operation
     */
    protected function getExpectedToolForRead(string $target): ?string
    {
        $target = Str::lower(trim($target));
        
        // Mapping: User-Request → Tool-Name
        $mappings = [
            'projekt' => 'planner.projects.GET',
            'projekte' => 'planner.projects.GET',
            'project' => 'planner.projects.GET',
            'projects' => 'planner.projects.GET',
            'aufgabe' => 'planner.tasks.GET',
            'aufgaben' => 'planner.tasks.GET',
            'task' => 'planner.tasks.GET',
            'tasks' => 'planner.tasks.GET',
            'slot' => 'planner.project_slots.GET',
            'slots' => 'planner.project_slots.GET',
            'team' => 'core.teams.GET',
            'teams' => 'core.teams.GET',
            'company' => 'crm.companies.GET',
            'companies' => 'crm.companies.GET',
            'unternehmen' => 'crm.companies.GET',
            'contact' => 'crm.contacts.GET',
            'contacts' => 'crm.contacts.GET',
            'kontakt' => 'crm.contacts.GET',
            'kontakte' => 'crm.contacts.GET',
        ];
        
        // Prüfe exakte Matches
        if (isset($mappings[$target])) {
            return $mappings[$target];
        }
        
        // Prüfe Teil-Strings (z.B. "alle projekte" enthält "projekte")
        foreach ($mappings as $key => $tool) {
            if (str_contains($target, $key)) {
                return $tool;
            }
        }
        
        return null;
    }
    
    /**
     * Findet ein ähnliches Tool (z.B. core.teams.GET statt planner.projects.GET)
     */
    protected function findSimilarTool(string $expectedTool, array $toolsCalled): ?string
    {
        // Wenn das erwartete Tool nicht aufgerufen wurde, prüfe ob ein ähnliches aufgerufen wurde
        // z.B. "core.teams.GET" statt "planner.projects.GET" wenn User "Projekte" wollte
        foreach ($toolsCalled as $tool) {
            if ($tool && $tool !== $expectedTool) {
                // Prüfe ob es ein GET-Tool ist (ähnliche Kategorie)
                if (str_ends_with($tool, '.GET')) {
                    return $tool;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Normalisiert Target-String (entfernt spezifische Kontexte)
     * 
     * Generisch: Entfernt Phrasen wie "im aktuellen Team", "aus dem Projekt", etc.
     * Macht die Verifikation generischer und weniger spezifisch
     */
    protected function normalizeTarget(?string $target): string
    {
        if (empty($target)) {
            return 'Elemente';
        }
        
        $normalized = Str::lower(trim($target));
        
        // Entferne spezifische Kontexte (generisch)
        $contextPatterns = [
            '/\s+im\s+aktuellen\s+team/i',
            '/\s+aus\s+dem\s+aktuellen\s+team/i',
            '/\s+im\s+team/i',
            '/\s+aus\s+dem\s+team/i',
            '/\s+aus\s+dem\s+projekt/i',
            '/\s+im\s+projekt/i',
            '/\s+des\s+projekts/i',
            '/\s+des\s+projektes/i',
        ];
        
        foreach ($contextPatterns as $pattern) {
            $normalized = preg_replace($pattern, '', $normalized);
        }
        
        // Entferne "doppelte" - ist redundant
        $normalized = preg_replace('/\s*doppelte?\s*/i', '', $normalized);
        
        // Trim und normalisiere
        $normalized = trim($normalized);
        
        return !empty($normalized) ? $normalized : 'Elemente';
    }
}

/**
 * Result einer Verifikation
 */
class VerificationResult
{
    protected bool $isOk;
    protected ?CompletenessCheck $completeness = null;
    protected ?ConsistencyCheck $consistency = null;
    protected ?Intention $intention = null;
    
    public static function ok(): self
    {
        $result = new self();
        $result->isOk = true;
        return $result;
    }
    
    public static function withIssues(CompletenessCheck $completeness, ConsistencyCheck $consistency, Intention $intention): self
    {
        $result = new self();
        $result->isOk = false;
        $result->completeness = $completeness;
        $result->consistency = $consistency;
        $result->intention = $intention;
        return $result;
    }
    
    public function isOk(): bool
    {
        return $this->isOk;
    }
    
    public function hasIssues(): bool
    {
        return !$this->isOk;
    }
    
    public function getIssuesText(): string
    {
        if ($this->isOk) {
            return '';
        }
        
        $issues = [];
        
        if ($this->completeness && !$this->completeness->isComplete()) {
            $issues[] = "Vollständigkeit: " . $this->completeness->getMessage();
        }
        
        if ($this->consistency && !$this->consistency->isConsistent()) {
            $issues[] = "Konsistenz: " . implode(', ', $this->consistency->getIssues());
        }
        
        return implode("\n", $issues);
    }
}

/**
 * Vollständigkeits-Check
 */
class CompletenessCheck
{
    protected bool $isComplete;
    protected ?string $message = null;
    
    public static function complete(): self
    {
        $check = new self();
        $check->isComplete = true;
        return $check;
    }
    
    public static function incomplete(string $message): self
    {
        $check = new self();
        $check->isComplete = false;
        $check->message = $message;
        return $check;
    }
    
    public function isComplete(): bool
    {
        return $this->isComplete;
    }
    
    public function getMessage(): ?string
    {
        return $this->message;
    }
}

/**
 * Konsistenz-Check
 */
class ConsistencyCheck
{
    protected bool $isConsistent;
    protected array $issues = [];
    
    public static function consistent(): self
    {
        $check = new self();
        $check->isConsistent = true;
        return $check;
    }
    
    public static function inconsistent(array $issues): self
    {
        $check = new self();
        $check->isConsistent = false;
        $check->issues = $issues;
        return $check;
    }
    
    public function isConsistent(): bool
    {
        return $this->isConsistent;
    }
    
    public function getIssues(): array
    {
        return $this->issues;
    }
}

