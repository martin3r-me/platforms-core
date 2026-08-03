<?php

namespace Platform\Core\Enums;

/**
 * Art eines kontextgebundenen Datums/Zeitpunkts.
 *
 * Wird von {@see \Platform\Core\Models\CoreContextDateTime} als `kind`
 * gespeichert. `custom` dient als Escape-Hatch für modul-spezifische Typen,
 * die (noch) keinen eigenen Case brauchen.
 */
enum ContextDateTimeKind: string
{
    case Start = 'start';
    case End = 'end';
    case Due = 'due';
    case Milestone = 'milestone';
    case Reminder = 'reminder';
    case Review = 'review';
    case Deadline = 'deadline';
    case Custom = 'custom';

    /**
     * Deutsche Bezeichnung für die Art.
     */
    public function label(): string
    {
        return match ($this) {
            self::Start => 'Start',
            self::End => 'Ende',
            self::Due => 'Fällig',
            self::Milestone => 'Meilenstein',
            self::Reminder => 'Erinnerung',
            self::Review => 'Review',
            self::Deadline => 'Deadline',
            self::Custom => 'Sonstiges',
        };
    }

    /**
     * Alle Arten als Array für Select-Optionen (value => label).
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case) => $carry + [$case->value => $case->label()],
            []
        );
    }

    /**
     * Alle Werte als Array (für Validierung).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
