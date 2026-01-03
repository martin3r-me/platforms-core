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
     * SELF-REFLECTION-PATTERN: Die LLM reflektiert selbst
     * - Was will die LLM lösen?
     * - Mit welchem Tool will sie das tun?
     * - Ist das Tool das richtige?
     * - Gibt es bessere Tools?
     * - Braucht sie überhaupt ein Tool?
     * 
     * Wir geben nur einen Self-Reflection-Prompt, die LLM entscheidet selbst (LOOSE)
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
        // Loop-Detection: Prüfe ob das Tool bereits mehrfach aufgerufen wurde
        $loopDetected = $this->detectLoop($toolName, $previousToolResults);
        
        // Erstelle Self-Reflection-Prompt für die LLM
        $reflectionPrompt = $this->buildSelfReflectionPrompt($userIntent, $toolName, $toolArguments, $previousToolResults);
        
        // Erstelle ToolMatch mit Self-Reflection-Hinweis
        $toolMatch = new ToolMatch();
        $toolMatch->toolName = $toolName;
        $toolMatch->isMatch = true; // Default: true (LOOSE - LLM entscheidet selbst)
        $toolMatch->confidence = 0.5;
        $toolMatch->reason = 'Self-Reflection: LLM prüft selbst, ob das Tool passt';
        $toolMatch->reflectionPrompt = $reflectionPrompt; // Für Debugging
        
        // Keine Intent-Extraktion mehr (LLM macht das selbst)
        $intention = new Intention();
        
        // Immer Self-Reflection-Hinweis geben (auch wenn keine Loop erkannt)
        // Die LLM soll sich immer fragen: "Ist das Tool das richtige?"
        // Aber wir markieren es nur als "Issue" wenn Loop erkannt wurde
        if ($loopDetected) {
            $result = PreFlightResult::withIssues(
                $toolMatch, 
                null, // Kein besseres Tool vorgeschlagen - LLM soll selbst suchen
                $loopDetected, 
                $intention
            );
            $result->setReflectionPrompt($reflectionPrompt);
            return $result;
        }
        
        // Auch bei OK geben wir Self-Reflection-Hinweis (aber nicht als Issue)
        // Die LLM kann selbst entscheiden, ob sie reflektieren will
        $result = PreFlightResult::ok();
        // Setze reflectionPrompt auch bei OK (für Controller)
        $result->setReflectionPrompt($reflectionPrompt);
        return $result;
    }
    
    /**
     * Baut einen Self-Reflection-Prompt für die LLM
     * 
     * Die LLM soll sich selbst fragen:
     * - Was will ich eigentlich lösen?
     * - Ist dieses Tool das richtige?
     * - Gibt es bessere Tools?
     * - Brauche ich überhaupt ein Tool?
     */
    protected function buildSelfReflectionPrompt(
        string $userIntent,
        string $toolName,
        array $toolArguments,
        array $previousToolResults
    ): string {
        $prompt = "🤔 **SELF-REFLECTION (Pre-Flight):**\n\n";
        $prompt .= "Bevor du das Tool '{$toolName}' aufrufst, reflektiere kurz:\n\n";
        $prompt .= "1. **Was will ich eigentlich lösen?**\n";
        $prompt .= "   User-Anfrage: {$userIntent}\n\n";
        $prompt .= "2. **Ist dieses Tool das richtige?**\n";
        $prompt .= "   Tool: {$toolName}\n";
        if (!empty($toolArguments)) {
            $prompt .= "   Argumente: " . json_encode($toolArguments, JSON_UNESCAPED_UNICODE) . "\n";
        }
        $prompt .= "\n";
        $prompt .= "3. **Gibt es bessere Tools?**\n";
        $prompt .= "   - Nutze 'tools.GET' um nach besseren Tools zu suchen\n";
        $prompt .= "   - Prüfe, ob ein anderes Tool besser zur Anfrage passt\n\n";
        $prompt .= "4. **Brauche ich überhaupt ein Tool?**\n";
        $prompt .= "   - Kann ich die Anfrage mit vorhandenen Informationen beantworten?\n";
        $prompt .= "   - Sind die vorherigen Tool-Results ausreichend?\n\n";
        
        if (!empty($previousToolResults)) {
            $prompt .= "**Vorherige Tool-Results:**\n";
            foreach (array_slice($previousToolResults, -3) as $result) {
                $tool = $result['tool'] ?? 'unknown';
                $success = $result['success'] ?? false;
                $prompt .= "- {$tool}: " . ($success ? '✅ Erfolgreich' : '❌ Fehlgeschlagen') . "\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "⚠️ **WICHTIG:** Du entscheidest selbst (LOOSE-Pattern). ";
        $prompt .= "Wenn du unsicher bist, nutze 'tools.GET' um nach besseren Tools zu suchen, ";
        $prompt .= "oder beantworte die Frage direkt mit vorhandenen Informationen.\n";
        
        return $prompt;
    }
    

    /**
     * Extrahiert die Intention aus der User-Nachricht
     */
    protected function extractIntention(string $userMessage): Intention
    {
        $message = strtolower(trim($userMessage));
        
        $intention = new Intention();
        
        // READ-Patterns - erweitert für bessere Erkennung
        // Pattern 1: "zeig/liste ... alle/die ..."
        if (preg_match('/(?:zeig|liste|gib|gebe).*?(?:alle|die|mir|bitte)?\s+(.+?)(?:\s+aus|\s+im|\s+von|\s+des|$)/i', $userMessage, $matches)) {
            $intention->type = 'read';
            $intention->target = trim($matches[1] ?? '');
        } 
        // Pattern 2: "alle/die ... aus/im/von/des"
        elseif (preg_match('/(?:alle|die)\s+(.+?)(?:\s+aus|\s+im|\s+von|\s+des|$)/i', $userMessage, $matches)) {
            $intention->type = 'read';
            $intention->target = trim($matches[1] ?? '');
        }
        // Pattern 3: "... slots und aufgaben ..." oder "... aufgaben und slots ..."
        elseif (preg_match('/(?:slots|aufgaben|tasks|project_slots).*?(?:und|,)?\s*(?:aufgaben|tasks|slots|project_slots)/i', $userMessage, $matches)) {
            $intention->type = 'read';
            $intention->target = 'slots und aufgaben';
        }
        // Pattern 4: "... slots ..." oder "... aufgaben ..."
        elseif (preg_match('/(?:slots|aufgaben|tasks|project_slots)/i', $userMessage, $matches)) {
            $intention->type = 'read';
            $intention->target = trim($matches[0] ?? '');
        }
        // Pattern 5: "... des Projektes" oder "... des Projekts"
        elseif (preg_match('/des\s+(?:projekt|projektes|projekts)/i', $userMessage, $matches)) {
            $intention->type = 'read';
            // Versuche mehr Kontext zu extrahieren
            if (preg_match('/(?:slots|aufgaben|tasks).*?des\s+(?:projekt|projektes|projekts)/i', $userMessage, $contextMatches)) {
                $intention->target = 'slots und aufgaben des projektes';
            } else {
                $intention->target = 'projekt';
            }
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
        
        // Slots und Aufgaben (kombiniert) - benötigt project_slots.GET
        if (preg_match('/(?:slots.*?aufgaben|aufgaben.*?slots|project_slots)/i', $target)) {
            return 'planner.project_slots.GET';
        }
        
        // Nur Slots
        if (preg_match('/^slots$|^project_slots$/i', $target)) {
            return 'planner.project_slots.GET';
        }
        
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
    protected ?string $reflectionPrompt = null;

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

    /**
     * Setzt den Self-Reflection-Prompt (LOOSE) damit Controller ihn immer anzeigen kann,
     * ohne dass PreFlight hart eingreift.
     */
    public function setReflectionPrompt(string $prompt): self
    {
        $this->reflectionPrompt = $prompt;
        return $this;
    }

    public function getReflectionPrompt(): ?string
    {
        return $this->reflectionPrompt;
    }

    public function getIssuesText(): string
    {
        $issues = [];

        // Loop-Detection (nur wenn Issue)
        if (!$this->isOk && $this->loopDetected) {
            $issues[] = "⚠️ LOOP ERKANNT: Das Tool '{$this->toolMatch->toolName}' wurde bereits mehrfach aufgerufen!";
            $issues[] = "💡 HINWEIS: Prüfe, ob du wirklich nochmal das gleiche Tool aufrufen musst, oder ob du mit den vorhandenen Tool-Results weiterarbeiten kannst.";
        }

        // Self-Reflection-Prompt (immer zeigen, wenn vorhanden - auch bei OK)
        $prompt = $this->reflectionPrompt ?? $this->toolMatch->reflectionPrompt ?? null;
        if ($prompt) {
            $issues[] = $prompt;
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
    public ?string $reflectionPrompt = null; // Self-Reflection-Prompt für die LLM

    public function isMatch(): bool
    {
        return $this->isMatch;
    }
}
