<?php

namespace Platform\Core\Verbalization\Pulse;

use Platform\Core\Verbalization\Enums\DataSource;
use Platform\Core\Verbalization\Enums\FactPriority;
use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\Template\NarrativeTemplate;

/**
 * Erzaehlvorlage fuer Entity-Pulse (Executive-Bericht).
 *
 * Dramaturgie bewusst schlank — der Pulse-Collector liefert schon vorstrukturierte
 * Facts (jeder Fact mit Quellen-Praefix wie "Projekt X — ..."). Das Template sortiert
 * nur nach Priority und ueberlaesst dem Verbalizer die Bogen-Bildung.
 */
class EntityPulseTemplate implements NarrativeTemplate
{
    public function handles(): string
    {
        return 'entity_pulse';
    }

    public function renderFactSheet(Subject $subject): string
    {
        $lines = [];
        $lines[] = '## ' . $subject->identity->primaryName;
        $lines[] = '';

        $core = $this->factsByPriority($subject, FactPriority::CORE);
        if (! empty($core)) {
            $lines[] = '### Highlights';
            foreach ($core as $f) {
                $lines[] = '- ' . $f->text;
            }
            $lines[] = '';
        }

        $qualifying = $this->factsByPriority($subject, FactPriority::QUALIFYING);
        if (! empty($qualifying)) {
            $lines[] = '### Weitere Kennzahlen';
            foreach ($qualifying as $f) {
                $lines[] = '- ' . $f->text;
            }
            $lines[] = '';
        }

        $lines[] = '### Daten-Grundlage';
        $lines[] = '- ' . $this->describeFreshness($subject);

        return implode("\n", $lines);
    }

    protected function describeFreshness(Subject $subject): string
    {
        $when = $subject->freshness->asOf->format('d.m.Y H:i');
        return match ($subject->freshness->source) {
            DataSource::LIVE => "Live-Daten (Stand: {$when}).",
            DataSource::SNAPSHOT => "Daten aus Snapshot vom {$when}.",
            DataSource::SNAPSHOT_WITH_LIVE_TOPUP => "Basis: Snapshot vom {$when}, ergaenzt um Live-Bewegungen seitdem.",
        };
    }

    /** @return \Platform\Core\Verbalization\Fact[] */
    protected function factsByPriority(Subject $subject, FactPriority $priority): array
    {
        return array_values(array_filter($subject->facts, fn ($f) => $f->priority === $priority));
    }
}
