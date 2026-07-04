<?php

namespace Platform\Core\Verbalization\Recipe;

use Platform\Core\Models\VerbalizationRecipe;
use Platform\Core\Verbalization\Enums\FactNature;

/**
 * Recipe als Read-Only-DTO.
 *
 * Vom DB-Model entkoppelt — Sammler und Verbalizer arbeiten mit dem DTO,
 * nicht mit Eloquent. Damit ist die Recipe leicht testbar (Fixture statt DB)
 * und Caching-freundlich.
 *
 * Source-Helper (hasSource / sourceConfig / sourceLimit) abstrahieren die
 * gaengigsten Zugriffs-Muster — Sammler brauchen nicht eigenhaendig
 * im sources-Array zu wuehlen.
 */
final class CollectionRecipe
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $subjectType,
        public readonly array $sources,
        public readonly array $style,
        public readonly ?array $guards,
        public readonly ?array $llm,
        public readonly ?array $includeNatures,
        public readonly ?string $freshnessRequirement,
        public readonly ?int $teamId,
    ) {}

    public static function fromModel(VerbalizationRecipe $m): self
    {
        return new self(
            key: $m->key,
            name: $m->name,
            description: $m->description,
            subjectType: $m->subject_type,
            sources: $m->sources ?? [],
            style: $m->style ?? [],
            guards: $m->guards,
            llm: $m->llm,
            includeNatures: $m->include_natures,
            freshnessRequirement: $m->freshness_requirement,
            teamId: $m->team_id,
        );
    }

    /**
     * Prueft, ob eine Fact-Nature laut Recipe zugelassen ist. Wenn includeNatures
     * leer/null ist, sind alle Naturen zugelassen (Standard).
     */
    public function includesNature(FactNature $nature): bool
    {
        if (empty($this->includeNatures)) {
            return true;
        }
        return in_array($nature->value, $this->includeNatures, true);
    }

    /**
     * Provider-Praeferenz der Recipe ("openai", "anthropic", ...) oder null wenn nicht gesetzt.
     */
    public function llmProvider(): ?string
    {
        $p = $this->llm['provider'] ?? null;
        return is_string($p) && $p !== '' ? $p : null;
    }

    /**
     * Modell-Praeferenz der Recipe (z.B. "gpt-4o-2024-08-06") oder null wenn nicht gesetzt.
     */
    public function llmModel(): ?string
    {
        $m = $this->llm['model'] ?? null;
        return is_string($m) && $m !== '' ? $m : null;
    }

    /**
     * Ist eine Quelle in dieser Recipe aktiv? Default true wenn key gesetzt,
     * false wenn key fehlt oder enabled=false steht.
     *
     * Akzeptiert beide Source-Formen:
     *  - "description": true                    (Boolean-Kurzform)
     *  - "frogs": { "enabled": true, ... }     (Objekt-Form mit Limits)
     */
    public function hasSource(string $key): bool
    {
        if (! array_key_exists($key, $this->sources)) {
            return false;
        }
        $value = $this->sources[$key];
        if (is_bool($value)) {
            return $value;
        }
        if (is_array($value)) {
            return (bool) ($value['enabled'] ?? true);
        }
        return false;
    }

    /**
     * Liefert die Konfiguration einer Quelle (Objekt-Form) oder leeres Array.
     */
    public function sourceConfig(string $key): array
    {
        if (! array_key_exists($key, $this->sources)) {
            return [];
        }
        $value = $this->sources[$key];
        return is_array($value) ? $value : [];
    }

    /**
     * Bequemer Zugriff auf Top-N / max-Limits einer Source.
     */
    public function sourceLimit(string $key, string $limitKey = 'top_n', ?int $default = null): ?int
    {
        $cfg = $this->sourceConfig($key);
        if (! isset($cfg[$limitKey])) {
            return $default;
        }
        return (int) $cfg[$limitKey];
    }
}
