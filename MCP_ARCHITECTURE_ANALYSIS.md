# MCP-Architektur Analyse & Best Practices

## Aktuelle Probleme

### 1. **ToolDiscoveryService.findByIntent() - Über-engineered**

**Problem:**
- Komplexe Keyword-Extraktion mit Bigrams führt zu Memory-Exhaustion
- Verschachtelte Loops über alle Tools × Keywords × Examples × Tags
- O(n²) oder O(n³) Komplexität
- **NICHT Teil des MCP-Standards**

**MCP Best Practice:**
- MCP sendet **ALLE verfügbaren Tools** an das LLM
- Das LLM entscheidet selbst, welches Tool es verwenden möchte
- Keine "intelligente" Filterung vorab

### 2. **Intent-basierte Discovery ist optional, nicht Standard**

**Aktueller Flow:**
```
User Message → ToolDiscoveryService.findByIntent() → Gefilterte Tools → OpenAI
```

**MCP Standard Flow:**
```
User Message → ALLE Tools → OpenAI → LLM entscheidet selbst
```

### 3. **Memory-Probleme durch komplexe Logik**

- Bigram-Generierung erzeugt exponentiell viele Keywords
- Verschachtelte Loops über große Arrays
- Keine Limits oder Early-Exit-Strategien

## Empfohlene Architektur (MCP Best Practice)

### 1. **Standard: Alle Tools anbieten**

```php
// OpenAiService.php - Standard-Verhalten
public function getAvailableTools(): array
{
    $registry = app(ToolRegistry::class);
    $allTools = $registry->all(); // ALLE Tools
    
    return array_map(
        fn($tool) => $this->convertToolToOpenAiFormat($tool),
        $allTools
    );
}
```

### 2. **Optional: Tool-Filterung nur wenn nötig**

```php
// ToolDiscoveryService.php - Vereinfacht
public function findByIntent(string $intent): array
{
    // WICHTIG: Nur wenn zu viele Tools (>50) vorhanden sind
    $allTools = $this->registry->all();
    if (count($allTools) <= 50) {
        return $allTools; // Keine Filterung nötig
    }
    
    // Einfache Keyword-Extraktion (ohne Bigrams)
    $keywords = $this->extractSimpleKeywords($intent);
    
    // Einfache Filterung basierend auf Tool-Namen
    return array_filter($allTools, function($tool) use ($keywords) {
        $toolName = strtolower($tool->getName());
        foreach ($keywords as $keyword) {
            if (stripos($toolName, $keyword) !== false) {
                return true;
            }
        }
        return false;
    });
}
```

### 3. **Vereinfachte Keyword-Extraktion**

```php
private function extractSimpleKeywords(string $intent): array
{
    // Einfache Aufteilung nach Leerzeichen
    $words = preg_split('/\s+/u', strtolower(trim($intent)), -1, PREG_SPLIT_NO_EMPTY);
    
    // Stop-Wörter entfernen
    $stopWords = ['ein', 'eine', 'der', 'die', 'das', 'und', 'oder', 'mit', 'für'];
    $keywords = array_filter($words, fn($w) => mb_strlen($w) > 2 && !in_array($w, $stopWords));
    
    // Maximal 10 Keywords (keine Bigrams!)
    return array_slice(array_values($keywords), 0, 10);
}
```

## Empfohlene Änderungen

### 1. **OpenAiService: Standardmäßig alle Tools**

```php
// Standard: Alle Tools anbieten
$tools = $this->getAvailableTools(); // Keine Filterung
```

### 2. **ToolDiscoveryService: Optional und vereinfacht**

- `findByIntent()` nur für spezielle Use-Cases (z.B. Playground)
- Standard-Flow verwendet alle Tools
- Vereinfachte Keyword-Extraktion ohne Bigrams

### 3. **Playground: Beide Modi anbieten**

- **Standard-Modus**: Alle Tools (MCP-konform)
- **Discovery-Modus**: Intent-basierte Filterung (optional)

## Fazit

**Aktuelles Problem:**
- `findByIntent()` ist zu komplex und nicht MCP-konform
- Memory-Exhaustion durch über-engineered Logik
- MCP sollte standardmäßig alle Tools anbieten

**Lösung:**
- Vereinfache `findByIntent()` drastisch
- Mache Discovery optional, nicht Standard
- Standard: Alle Tools an OpenAI senden (MCP-konform)

