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
}
