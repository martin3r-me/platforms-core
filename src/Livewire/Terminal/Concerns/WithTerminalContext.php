<?php

namespace Platform\Core\Livewire\Terminal\Concerns;

use Livewire\Attributes\On;

trait WithTerminalContext
{
    public ?string $contextType = null;
    public ?int $contextId = null;
    public ?string $contextSubject = null;
    public ?string $contextSource = null;
    public ?string $contextUrl = null;
    public array $contextMeta = [];

    #[On('terminal-context-changed')]
    public function receiveTerminalContext(
        ?string $contextType = null,
        ?int $contextId = null,
        ?string $contextSubject = null,
        ?string $contextSource = null,
        ?string $contextUrl = null,
        array $contextMeta = [],
    ): void {
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->contextSubject = $contextSubject;
        $this->contextSource = $contextSource;
        $this->contextUrl = $contextUrl;
        $this->contextMeta = $contextMeta;

        $this->onContextChanged();
    }

    /**
     * Override in child components to react to context changes.
     */
    protected function onContextChanged(): void
    {
        // no-op by default
    }

    protected function teamId(): ?int
    {
        return auth()->user()?->currentTeam?->id;
    }

    protected function hasContext(): bool
    {
        return $this->contextType !== null && $this->contextId !== null;
    }
}
