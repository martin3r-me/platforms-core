<?php

namespace Platform\Core\Livewire\Terminal;

use Livewire\Component;

/**
 * Trivial nested app rendered by the Terminal shell via TerminalAppRegistry.
 * Verification-only counterpart to Platform\Core\Terminal\Apps\DemoTerminalApp.
 */
class Demo extends Component
{
    public ?string $contextType = null;
    public ?int $contextId = null;
    public ?string $contextSubject = null;
    public ?string $contextSource = null;
    public ?string $contextUrl = null;
    public array $contextMeta = [];

    public function render()
    {
        return view('platform::livewire.terminal.demo');
    }
}
