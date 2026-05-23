<?php

namespace Platform\Core\Contracts;

use Illuminate\Support\Carbon;

class CashflowSignalDto
{
    public function __construct(
        public readonly string $providerKey,
        public readonly string $externalId,
        public readonly string $label,
        public readonly string $direction,
        public readonly float $amount,
        public readonly Carbon $expectedDate,
        public readonly float $confidence,
        public readonly string $confidenceLevel,
        public readonly ?string $counterparty = null,
        public readonly ?string $category = null,
        public readonly ?string $url = null,
        public readonly array $meta = [],
    ) {}
}
