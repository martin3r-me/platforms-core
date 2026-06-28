<?php

namespace Platform\Core\Verbalization\Enums;

enum FactPriority: string
{
    case CORE = 'core';
    case QUALIFYING = 'qualifying';
    case CONTEXT = 'context';
}
