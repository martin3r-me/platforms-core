<?php

namespace Platform\Core\Verbalization\Enums;

enum Confidence: string
{
    case VERIFIED = 'verified';
    case PLAUSIBLE = 'plausible';
    case ROUGH = 'rough';
}
