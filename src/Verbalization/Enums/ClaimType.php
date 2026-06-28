<?php

namespace Platform\Core\Verbalization\Enums;

enum ClaimType: string
{
    case SYSTEM_VERIFIED = 'system_verified';
    case SELF_REPORTED = 'self_reported';
    case THIRD_PARTY = 'third_party';
    case INFERRED = 'inferred';
}
