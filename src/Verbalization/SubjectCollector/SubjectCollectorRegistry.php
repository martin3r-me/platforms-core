<?php

namespace Platform\Core\Verbalization\SubjectCollector;

/**
 * Registry fuer Subject-Collectors.
 *
 * Module registrieren ihre Collectors im ServiceProvider::boot.
 * FeedService fragt fuer einen subject_type den passenden Collector ab.
 *
 * Lose Kopplung: core kennt keine Modul-Collectors. Module melden sich an.
 */
class SubjectCollectorRegistry
{
    /** @var array<string, SubjectCollectorInterface> */
    protected array $collectors = [];

    public function register(SubjectCollectorInterface $collector): void
    {
        $this->collectors[$collector->handles()] = $collector;
    }

    public function resolve(string $subjectType): ?SubjectCollectorInterface
    {
        return $this->collectors[$subjectType] ?? null;
    }

    public function registered(): array
    {
        return array_keys($this->collectors);
    }
}
