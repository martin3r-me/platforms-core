<?php

namespace Platform\Core\Contracts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface CashflowSignalProviderInterface
{
    /**
     * Unique key for this provider (e.g. 'invoices', 'sales_pipeline').
     */
    public function key(): string;

    /**
     * Human-readable label (e.g. 'Offene Rechnungen').
     */
    public function label(): string;

    /**
     * Priority for ordering (lower = higher priority).
     */
    public function priority(): int;

    /**
     * Return signals for the given team and date range.
     *
     * @return Collection<CashflowSignalDto>
     */
    public function signals(int $teamId, Carbon $from, Carbon $to): Collection;

    /**
     * Check if a signal has been resolved (e.g. invoice paid).
     * Returns null if the provider cannot determine the status.
     */
    public function isResolved(int $teamId, string $externalId): ?bool;
}
