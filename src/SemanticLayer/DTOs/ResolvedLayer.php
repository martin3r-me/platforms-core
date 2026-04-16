<?php

namespace Platform\Core\SemanticLayer\DTOs;

/**
 * Immutable Resultat des SemanticLayerResolvers.
 *
 * Liefert den fertig gemergten Layer (Core + ggf. Team-Extension),
 * inklusive Rendered-Prompt-Block und Scope/Version-Chain.
 */
final class ResolvedLayer
{
    public function __construct(
        public readonly string $perspektive,
        public readonly array $ton,
        public readonly array $heuristiken,
        public readonly array $negativ_raum,
        public readonly array $scope_chain,
        public readonly array $version_chain,
        public readonly int $token_count,
        public readonly ?string $rendered_block,
    ) {
    }

    public static function empty(): self
    {
        return new self(
            perspektive: '',
            ton: [],
            heuristiken: [],
            negativ_raum: [],
            scope_chain: [],
            version_chain: [],
            token_count: 0,
            rendered_block: null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->rendered_block === null
            && $this->perspektive === ''
            && $this->ton === []
            && $this->heuristiken === []
            && $this->negativ_raum === [];
    }

    public function toArray(): array
    {
        return [
            'perspektive' => $this->perspektive,
            'ton' => $this->ton,
            'heuristiken' => $this->heuristiken,
            'negativ_raum' => $this->negativ_raum,
            'scope_chain' => $this->scope_chain,
            'version_chain' => $this->version_chain,
            'token_count' => $this->token_count,
            'rendered_block' => $this->rendered_block,
        ];
    }
}
