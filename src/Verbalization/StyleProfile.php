<?php

namespace Platform\Core\Verbalization;

/**
 * Stilparameter — wie die Prosa klingt. Veraendert nie WAS drinsteht.
 *
 * Pro Kanal anders: Kundenfeed = formal/Sie/flowing,
 * internes Briefing = collegial/du/short.
 */
final class StyleProfile
{
    public function __construct(
        public readonly string $address = 'sie',          // 'du' | 'sie'
        public readonly string $tone = 'formal',          // 'formal' | 'collegial' | 'evocative'
        public readonly string $rhythm = 'flowing',       // 'short' | 'flowing'
        public readonly string $perspective = 'observer', // 'observer' | 'self'
        public readonly ?string $semanticLayer = null,    // BHG-Block aus core.context.GET
        public readonly ?string $extraInstruction = null, // pro Kanal eigene Zusatzregel
    ) {}

    public static function formal(): self
    {
        return new self();
    }

    public static function collegial(): self
    {
        return new self(address: 'du', tone: 'collegial', rhythm: 'short');
    }

    /**
     * Builder: gibt eine Kopie zurueck mit neuem Semantic Layer (rendered_block aus
     * SemanticLayerResolver). Bewusst Inject-Pattern — StyleProfile kennt den Resolver
     * NICHT, das macht der Caller (= Tool, das den Team-Kontext kennt).
     */
    public function withSemanticLayer(?string $renderedBlock): self
    {
        return new self(
            address: $this->address,
            tone: $this->tone,
            rhythm: $this->rhythm,
            perspective: $this->perspective,
            semanticLayer: $renderedBlock,
            extraInstruction: $this->extraInstruction,
        );
    }

    public function withExtraInstruction(?string $instruction): self
    {
        return new self(
            address: $this->address,
            tone: $this->tone,
            rhythm: $this->rhythm,
            perspective: $this->perspective,
            semanticLayer: $this->semanticLayer,
            extraInstruction: $instruction,
        );
    }
}
