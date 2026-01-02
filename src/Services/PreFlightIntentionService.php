<?php

namespace Platform\Core\Services;

use Platform\Core\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Pre-Flight Intention Verification Service
 * 
 * Prüft BEVOR ein Tool ausgeführt wird, ob:
 * 1. Das Tool wirklich das richtige ist für die User-Intention
 * 2. Das Tool mit hoher Wahrscheinlichkeit zum Ziel führt
 * 3. Es nicht ein besser passendes Tool gibt
 * 
 * Verhindert Loops und falsche Tool-Auswahl
 */
class PreFlightIntentionService
{
    protected ?OpenAiService $openAiService = null;
    protected ?ToolRegistry $toolRegistry = null;

    public function __construct()
    {
        try {
            $this->openAiService = app(OpenAiService::class);
        } catch (\Throwable $e) {
            // Service nicht verfügbar - Pattern-Matching funktioniert trotzdem
        }
        
        try {
            $this->toolRegistry = app(ToolRegistry::class);
        } catch (\Throwable $e) {
            // Registry nicht verfügbar
        }
    }

    /**
     * Prüft BEVOR ein Tool ausgeführt wird, ob es das richtige ist
     * 
     * @param string $userIntent Die ursprüngliche User-Anfrage
     * @param string $toolName Das Tool, das aufgerufen werden soll
     * @param array $toolArguments Die Argumente für das Tool
     * @param array $previousToolResults Vorherige Tool-Results (für Kontext)
     * @return PreFlightResult
     */
    public function verify(
        string $userIntent,
        string $toolName,
        array $toolArguments = [],
        array $previousToolResults = []
    ): PreFlightResult {
        // 1. Extrahiere Intention aus User-Request
        $intention = $this->extractIntention($userIntent);
        
        // 2. Prüfe ob das Tool zur Intention passt
        $toolMatch = $this->checkToolMatch($intention, $toolName, $previousToolResults);
        
        // 3. Prüfe ob es ein besser passendes Tool gibt
        $betterTool = $this->findBetterTool($intention, $toolName, $previousToolResults);
        
        // 4. Prüfe ob das Tool bereits mehrfach aufgerufen wurde (Loop-Detection)
        $loopDetected = $this->detectLoop($toolName, $previousToolResults);
        
        // 5. Erstelle Result
        if (!$toolMatch->isMatch() || $betterTool !== null || $loopDetected) {
            return PreFlightResult::withIssues($toolMatch, $betterTool, $loopDetected, $intention);
        }
        
        return PreFlightResult::ok();
    }

    /**
     * Extrahiert die Intention aus der User-Nachricht
     */
    protected function extractIntention(string $userMessage): Intention
    {
        $message = strtolower(trim($userMessage));
        
        $intention = new Intention();
        
        // READ-Patterns
        if (preg_match('/zeig.*?(?:alle|die)\s+(.+?)(?:\s+aus|\s+im|\s+von|\s+des|$)/i', $userMessage, $matches)) {
            $intention->type = 'read';
            $intention->target = trim($matches[1] ?? '');
        } elseif (preg_match('/list.*?(?:alle|die)\s+(.+?)(?:\s+aus|\s+im|\s+von|\s+des|$)/i', $userMessage, $matches)) {
            $intention->type = 'read';
            $intention->target = trim($matches[1] ?? '');
        } elseif (preg_match('/(?:alle|die)\s+(.+?)(?:\s+aus|\s+im|\s+von|\s+des|$)/i', $userMessage, $matches)) {
            $intention->type = 'read';
            $intention->target = trim($matches[1] ?? '');
        }
        
        return $intention;
    }

    /**
     * Prüft ob das Tool zur Intention passt
     */
    protected function checkToolMatch(Intention $intention, string $toolName, array $previousToolResults): ToolMatch
    {
        $match = new ToolMatch();
        $match->toolName = $toolName;
        
        // Wenn keine Intention extrahiert wurde, können wir nicht prüfen
        if ($intention->isEmpty()) {
            $match->confidence = 0.5; // Neutral
            $match->reason = 'Keine klare Intention erkannt';
            return $match;
        }
        
        // READ-Operationen: Prüfe ob das Tool passt
        if ($intention->type === 'read' && $intention->target) {
            $target = strtolower($intention->target);
            
            // Mapping: User-Request → erwartetes Tool
            $expectedTool = $this->getExpectedToolForRead($target);
            
            if ($expectedTool && $toolName === $expectedTool) {
                $match->isMatch = true;
                $match->confidence = 0.9;
                $match->reason = "Tool passt zur Intention: '{$target}' → {$toolName}";
            } elseif ($expectedTool && $toolName !== $expectedTool) {
                $match->isMatch = false;
                $match->confidence = 0.3;
                $match->reason = "Falsches Tool: Erwartet '{$expectedTool}', aber '{$toolName}' wird aufgerufen. User wollte: '{$target}'";
                $match->expectedTool = $expectedTool;
            } else {
                // Kein erwartetes Tool gefunden - neutral
                $match->confidence = 0.5;
                $match->reason = "Kann nicht prüfen ob Tool passt";
            }
        } else {
            // Andere Operationen: Neutral
            $match->confidence = 0.5;
            $match->reason = 'Intention-Typ nicht unterstützt für Pre-Flight-Check';
        }
        
        return $match;
    }

    /**
     * Findet ein besser passendes Tool
     */
    protected function findBetterTool(Intention $intention, string $currentTool, array $previousToolResults): ?string
    {
        if ($intention->type === 'read' && $intention->target) {
            $target = strtolower($intention->target);
            $expectedTool = $this->getExpectedToolForRead($target);
            
            if ($expectedTool && $expectedTool !== $currentTool) {
                return $expectedTool;
            }
        }
        
        return null;
    }

    /**
     * Erwartetes Tool für READ-Operationen
     */
    protected function getExpectedToolForRead(string $target): ?string
    {
        $target = strtolower(trim($target));
        
        // Projekte
        if (preg_match('/projekt/i', $target)) {
            return 'planner.projects.GET';
        }
        
        // Aufgaben/Tasks
        if (preg_match('/(?:aufgabe|task)/i', $target)) {
            return 'planner.tasks.GET';
        }
        
        // Companies
        if (preg_match('/(?:compan|unternehmen|firma)/i', $target)) {
            return 'crm.companies.GET';
        }
        
        // Contacts
        if (preg_match('/(?:contact|kontakt)/i', $target)) {
            return 'crm.contacts.GET';
        }
        
        // Teams
        if (preg_match('/(?:team)/i', $target)) {
            return 'core.teams.GET';
        }
        
        return null;
    }

    /**
     * Erkennt Loops (gleiches Tool mehrfach aufgerufen)
     */
    protected function detectLoop(string $toolName, array $previousToolResults): bool
    {
        $count = 0;
        foreach ($previousToolResults as $result) {
            if (($result['tool'] ?? '') === $toolName) {
                $count++;
            }
        }
        
        // Loop erkannt wenn Tool bereits 2+ mal aufgerufen wurde
        return $count >= 2;
    }
}

/**
 * Result einer Pre-Flight-Verification
 */
class PreFlightResult
{
    protected bool $isOk;
    protected ?ToolMatch $toolMatch = null;
    protected ?string $betterTool = null;
    protected bool $loopDetected = false;
    protected ?Intention $intention = null;

    public static function ok(): self
    {
        $result = new self();
        $result->isOk = true;
        return $result;
    }

    public static function withIssues(
        ToolMatch $toolMatch,
        ?string $betterTool,
        bool $loopDetected,
        Intention $intention
    ): self {
        $result = new self();
        $result->isOk = false;
        $result->toolMatch = $toolMatch;
        $result->betterTool = $betterTool;
        $result->loopDetected = $loopDetected;
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

        if ($this->loopDetected) {
            $issues[] = "⚠️ LOOP ERKANNT: Das Tool '{$this->toolMatch->toolName}' wurde bereits mehrfach aufgerufen!";
        }

        if (!$this->toolMatch->isMatch()) {
            $issues[] = "❌ FALSCHES TOOL: {$this->toolMatch->reason}";
            if ($this->toolMatch->expectedTool) {
                $issues[] = "✅ RICHTIGES TOOL: Rufe stattdessen '{$this->toolMatch->expectedTool}' auf!";
            }
        }

        if ($this->betterTool) {
            $issues[] = "💡 BESSERES TOOL: '{$this->betterTool}' wäre besser geeignet als '{$this->toolMatch->toolName}'";
        }

        return implode("\n", $issues);
    }
}

/**
 * Tool-Match-Result
 */
class ToolMatch
{
    public string $toolName = '';
    public bool $isMatch = false;
    public float $confidence = 0.0;
    public string $reason = '';
    public ?string $expectedTool = null;

    public function isMatch(): bool
    {
        return $this->isMatch;
    }
}
