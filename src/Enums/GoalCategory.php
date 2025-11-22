<?php

namespace Platform\Core\Enums;

enum GoalCategory: string
{
    case Focus = 'focus';
    case Team = 'team';
    case Health = 'health';
    case Learning = 'learning';
    case Project = 'project';
    case Personal = 'personal';
    case Growth = 'growth';

    /**
     * Deutsche Bezeichnung für die Kategorie
     */
    public function label(): string
    {
        return match ($this) {
            self::Focus => 'Fokus',
            self::Team => 'Team',
            self::Health => 'Gesundheit',
            self::Learning => 'Lernen',
            self::Project => 'Projekt',
            self::Personal => 'Persönlich',
            self::Growth => 'Wachstum',
        };
    }

    /**
     * Icon für die Kategorie (optional, für zukünftige UI-Verbesserungen)
     */
    public function icon(): string
    {
        return match ($this) {
            self::Focus => 'heroicon-o-eye',
            self::Team => 'heroicon-o-users',
            self::Health => 'heroicon-o-heart',
            self::Learning => 'heroicon-o-academic-cap',
            self::Project => 'heroicon-o-folder',
            self::Personal => 'heroicon-o-user',
            self::Growth => 'heroicon-o-chart-bar',
        };
    }

    /**
     * Alle Kategorien als Array für Select-Optionen
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn(array $carry, self $case) => $carry + [$case->value => $case->label()],
            []
        );
    }

    /**
     * Alle Kategorie-Werte als Array (für Validierung)
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

