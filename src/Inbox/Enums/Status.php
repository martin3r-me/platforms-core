<?php

namespace Platform\Core\Inbox\Enums;

/**
 * Triage-Zustand eines Items — der kostbare, user-eigene Teil (nicht wegwerfbar).
 */
enum Status: string
{
    case New = 'new';
    case Done = 'done';
    case Ignored = 'ignored';
    case Snoozed = 'snoozed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Done => 'Erledigt',
            self::Ignored => 'Ignoriert',
            self::Snoozed => 'Snoozed',
            self::Archived => 'Archiviert',
        };
    }
}
