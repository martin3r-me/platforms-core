<?php

namespace Platform\Core\Enums;

enum StandardRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    /**
     * Alle Rollen, die Schreibzugriff haben
     */
    public static function getWriteRoles(): array
    {
        return [
            self::OWNER->value,
            self::ADMIN->value,
            self::MEMBER->value,
        ];
    }

    /**
     * Alle Rollen, die Leszugriff haben
     */
    public static function getReadRoles(): array
    {
        return [
            self::OWNER->value,
            self::ADMIN->value,
            self::MEMBER->value,
            self::VIEWER->value,
        ];
    }

    /**
     * Nur Owner-Rolle
     */
    public static function getOwnerRoles(): array
    {
        return [self::OWNER->value];
    }

    /**
     * Admin und Owner Rollen
     */
    public static function getAdminRoles(): array
    {
        return [
            self::OWNER->value,
            self::ADMIN->value,
        ];
    }
}
