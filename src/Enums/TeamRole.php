<?php

namespace Platform\Core\Enums;

enum TeamRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
}