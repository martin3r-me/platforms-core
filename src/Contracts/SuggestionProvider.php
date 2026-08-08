<?php

namespace Platform\Core\Contracts;

/**
 * SuggestionProvider — PORT (Ports & Adapters).
 *
 * Ein Wissens-/Vorschlags-Dienst liefert zu einem Kontext (Freitext + optionale Hinweise)
 * rangierte, strukturierte Kandidaten mit Konfidenz + Quelle. Consumer (customer GBU-Autor,
 * occupational Vorsorge-Matching, später Labor) hängen NUR an diesem Port — nie an der
 * Umsetzung. Heute lokaler Adapter (knowledge-Modul, Embeddings); morgen Remote-Adapter
 * (externer sovra-Dienst) — gleicher Port, Consumer unberührt.
 *
 * Rückgabe je Kandidat:
 *  [
 *    'type'       => 'arbmedvv_occasion'|'hazard'|…,
 *    'ref'        => ?int,          // ID im Zieltyp, falls auflösbar
 *    'label'      => 'Lärm …',
 *    'care_type'  => ?string,       // mandatory|offered|request|follow_up
 *    'confidence' => 0.0..1.0,
 *    'source'     => ?string,       // Herkunft (Regelwerk/Katalog), für Nachvollziehbarkeit
 *    'meta'       => array,
 *  ]
 */
interface SuggestionProvider
{
    /**
     * @param  array<string,mixed> $opts  z.B. ['types'=>['arbmedvv_occasion'], 'limit'=>8, 'entity_id'=>…]
     * @return array<int,array<string,mixed>>
     */
    public function suggest(int $teamId, string $context, array $opts = []): array;
}
