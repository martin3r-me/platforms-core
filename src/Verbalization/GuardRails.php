<?php

namespace Platform\Core\Verbalization;

/**
 * Nicht verhandelbare Grenzen. Gewinnen IMMER gegen Style.
 *
 * V1: alle vier hardcoded true. Konfigurierbar machen wir spaeter,
 * wenn ein konkreter Use-Case einen Rail anders setzen will.
 */
final class GuardRails
{
    public function __construct(
        public readonly bool $factsOnly = true,
        public readonly bool $admitGaps = true,
        public readonly bool $noSpeculation = true,
        public readonly bool $consistent = true,
    ) {}

    /**
     * Als kompakte Liste fuer den System-Prompt.
     */
    public function asPromptRules(): string
    {
        $rules = [];
        if ($this->factsOnly) {
            $rules[] = '- Verwende AUSSCHLIESSLICH die unten gelieferten Fakten. Erfinde nichts hinzu. Lass nichts wesentliches weg.';
        }
        if ($this->admitGaps) {
            $rules[] = '- Wo Daten fehlen: benenne die Luecke offen ("dazu liegen uns keine Daten vor") statt zu raten.';
        }
        if ($this->noSpeculation) {
            $rules[] = '- Keine Mutmassungen, kein "vermutlich" / "wahrscheinlich" / "koennte". Nur was belegt ist.';
        }
        if ($this->consistent) {
            $rules[] = '- Eine Aussage in mehreren Saetzen muss konsistent bleiben — keine Widersprueche.';
        }
        return implode("\n", $rules);
    }
}
