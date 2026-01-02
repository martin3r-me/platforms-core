<?php

namespace Platform\Core\Services;

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

