<?php

namespace Platform\Core\Verbalization\Enums;

enum SubjectKind: string
{
    case STATE = 'state';
    case MOVEMENT = 'movement';
    case COMPOSITE = 'composite';
}
