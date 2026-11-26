<?php

namespace Platform\Core\Support;

/**
 * Die Anforderung, die ein Host an Meetings stellt, die an ihm hängen — z. B. der
 * LA-Leitfaden eines Gremiums oder die Regeln eines Boards. Abrufbar, NICHT kopiert:
 * das Objekt lebt beim Host (Governance-Modul) und wird vom Meeting nur gelesen,
 * damit Mensch wie Agent sehen, was hier gefordert ist.
 *
 * Bewusst anzeige-/lesefertig und modell-unabhängig: das Meeting-Modul kennt nur
 * dieses Wertobjekt, keine Host-Klassen.
 */
final class MeetingBriefing
{
    /**
     * @param string                                              $title    z. B. „LA-Leitfaden"
     * @param string|null                                         $summary  kurzer Zweck/Anlass
     * @param list<string|array{label: string, hint?: ?string}>   $points   Leitfragen/Kriterien (Text oder label+hint)
     * @param string|null                                         $url      tiefergehender Link zur Quelle
     * @param string|null                                         $icon     Heroicon-Name ohne Prefix (z. B. „academic-cap")
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $summary = null,
        public readonly array $points = [],
        public readonly ?string $url = null,
        public readonly ?string $icon = null,
    ) {}

    /** Punkte normalisiert als [label, hint] — die View muss nicht zwei Formen kennen. */
    public function normalizedPoints(): array
    {
        $out = [];
        foreach ($this->points as $point) {
            if (is_array($point)) {
                $label = trim((string) ($point['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $out[] = ['label' => $label, 'hint' => isset($point['hint']) ? trim((string) $point['hint']) ?: null : null];
            } else {
                $label = trim((string) $point);
                if ($label !== '') {
                    $out[] = ['label' => $label, 'hint' => null];
                }
            }
        }

        return $out;
    }
}
