<?php

namespace Platform\Core\Verbalization;

use Platform\Core\Verbalization\Enums\SubjectKind;

/**
 * Subject — der EINE Eingang in den Verbalizer.
 *
 * Wird vom domaenenspezifischen Sammler befuellt, vom Verbalizer
 * konsumiert. Der Verbalizer kennt keine Module, keine DB, keine
 * Eloquent-Models — nur diese Klasse.
 *
 * Disziplin: was hier nicht drinsteht, darf in der Prosa nicht auftauchen.
 *
 * V1 nutzt nur kind=STATE. MOVEMENT und COMPOSITE sind in den Enums
 * vorbereitet, aber noch nicht befuellt:
 *  - $movement bleibt null
 *  - $children bleibt []
 */
final class Subject
{
    /**
     * @param  Fact[]  $facts
     * @param  Edge[]  $edges
     * @param  Subject[]  $children
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly SubjectKind $kind,
        public readonly string $type,
        public readonly string $id,
        public readonly Identity $identity,
        public readonly array $facts,
        public readonly array $edges,
        public readonly Freshness $freshness,
        public readonly mixed $movement = null,
        public readonly array $children = [],
        public readonly array $meta = [],
    ) {}

    /**
     * Inhalts-Hash ueber Identity + Facts + Edges (OHNE Freshness, OHNE meta).
     *
     * Zweck: Dedup beim Feed-Refresh — wenn der State sich nicht veraendert hat,
     * wird kein neuer Output erzeugt (sonst Spam im RSS-Reader).
     *
     * Nicht enthalten:
     *  - Freshness (taken_at aendert sich auch ohne Substanz)
     *  - meta (Debug-Info, nicht inhaltlich)
     *  - movement / children (V1 nicht aktiv, kommen mit eigenem Hash)
     */
    public function stateHash(): string
    {
        $parts = [
            'type' => $this->type,
            'id' => $this->id,
            'identity' => [
                'primary' => $this->identity->primaryName,
                'short' => $this->identity->shortLabel,
            ],
            'facts' => collect($this->facts)
                ->map(fn ($f) => $f->priority->value . '|' . $f->nature->value . '|' . $f->text)
                ->sort()
                ->values()
                ->all(),
            'edges' => collect($this->edges)
                ->map(fn ($e) => implode('|', [
                    $e->relation,
                    $e->targetType,
                    (string) $e->targetId,
                    $e->targetLabel,
                    $e->weight->value,
                    $e->claim->type->value,
                    $e->claim->level->value,
                ]))
                ->sort()
                ->values()
                ->all(),
        ];
        return hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Liefert eine Kopie des Subjects mit gefilterten Facts. Andere Felder bleiben
     * gleich (Identity/Edges/Freshness). Wird vom Verbalizer genutzt, um Recipe-Filter
     * (z.B. include_natures) vor dem Rendering anzuwenden — der Collector selbst sammelt
     * immer alles, was er hat.
     */
    public function withFilteredFacts(callable $keep): self
    {
        return new self(
            kind: $this->kind,
            type: $this->type,
            id: $this->id,
            identity: $this->identity,
            facts: array_values(array_filter($this->facts, $keep)),
            edges: $this->edges,
            freshness: $this->freshness,
            movement: $this->movement,
            children: $this->children,
            meta: $this->meta,
        );
    }
}
