<?php

namespace Platform\Core\Verbalization\Enums;

enum DataSource: string
{
    case LIVE = 'live';
    case SNAPSHOT = 'snapshot';
    case SNAPSHOT_WITH_LIVE_TOPUP = 'snapshot_with_live_topup';
}
