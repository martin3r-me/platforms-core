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
        
        // 2. Prüfe Vollständigkeit basierend auf Action Summary
        $completeness = $this->checkCompleteness($intention, $actionSummary);
        
        // 3. Prüfe Konsistenz (z.B. "Ich habe X erstellt" vs. tatsächlich erstellt)
        $consistency = $this->checkConsistency($intention, $toolResults, $actionSummary);
        
        // 4. Wenn Probleme gefunden: Erstelle Verifikations-Result
        if (!$completeness->isComplete() || !$consistency->isConsistent()) {
            return VerificationResult::withIssues($completeness, $consistency, $intention);
        }
        
        return VerificationResult::ok();
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
        
        // LIST/GET-Patterns (keine Verifikation nötig)
        if (preg_match('/(?:zeige?|liste?|hole?|get)\s+(?:alle\s+)?(.+?)(?:\s+aus|\s+im|\s+von|$)/i', $message)) {
            $intention->type = 'read';
            // Read-Operationen brauchen keine Verifikation
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
     * Prüft Vollständigkeit basierend auf Action Summary
     */
    protected function checkCompleteness(Intention $intention, array $actionSummary): CompletenessCheck
    {
        if ($intention->isEmpty() || $intention->type === 'read') {
            return CompletenessCheck::complete();
        }
        
        $modelsCreated = $actionSummary['models_created'] ?? 0;
        $modelsUpdated = $actionSummary['models_updated'] ?? 0;
        $modelsDeleted = $actionSummary['models_deleted'] ?? 0;
        
        // DELETE-Check
        if ($intention->type === 'delete') {
            if ($intention->isAll) {
                // "Lösche alle X" - prüfe ob überhaupt etwas gelöscht wurde
                if ($modelsDeleted === 0) {
                    return CompletenessCheck::incomplete(
                        "Es sollten alle '{$intention->target}' gelöscht werden, aber es wurde nichts gelöscht."
                    );
                }
            } elseif ($intention->expectedCount !== null) {
                // "Lösche 3 X" - prüfe ob genau 3 gelöscht wurden
                if ($modelsDeleted < $intention->expectedCount) {
                    return CompletenessCheck::incomplete(
                        "Es sollten {$intention->expectedCount} '{$intention->target}' gelöscht werden, aber nur {$modelsDeleted} wurden gelöscht."
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
     * Prüft Konsistenz zwischen Tool-Results und Action Summary
     */
    protected function checkConsistency(Intention $intention, array $toolResults, array $actionSummary): ConsistencyCheck
    {
        $issues = [];
        
        // Prüfe ob alle Tool-Calls erfolgreich waren
        $failedTools = [];
        foreach ($toolResults as $result) {
            if (!($result['success'] ?? false)) {
                $failedTools[] = $result['tool'] ?? 'Unbekannt';
            }
        }
        
        if (!empty($failedTools)) {
            $issues[] = "Einige Tools sind fehlgeschlagen: " . implode(', ', $failedTools);
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
}

/**
 * Repräsentiert eine extrahierte Intention
 */
class Intention
{
    public ?string $type = null; // 'delete', 'create', 'update', 'read'
    public ?string $target = null; // Was soll geändert werden (z.B. "Testaufgaben")
    public ?int $expectedCount = null; // Erwartete Anzahl
    public bool $isAll = false; // "alle" = true
    
    public function isEmpty(): bool
    {
        return $this->type === null;
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

