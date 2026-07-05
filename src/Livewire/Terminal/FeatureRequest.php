<?php

namespace Platform\Core\Livewire\Terminal;

use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;

/**
 * Terminal tab that lets a user file a feature request against the dev package
 * of the module they are currently in. Talks to the dev module in-process via
 * DevFeatureRequestService, guarded so core degrades gracefully if the dev
 * module is not installed.
 */
class FeatureRequest extends Component
{
    use WithTerminalContext;

    private const SERVICE = \Platform\Dev\Services\DevFeatureRequestService::class;

    public string $title = '';
    public string $description = '';
    public string $priority = 'normal';
    public ?int $packageId = null;
    public bool $attachContext = true;

    /** @var array<int, array{id:int,name:string}> */
    public array $packageOptions = [];
    public bool $autoResolved = false;
    public ?string $moduleKey = null;
    public bool $devAvailable = true;

    public function mount(): void
    {
        $this->devAvailable = class_exists(self::SERVICE);

        if ($this->devAvailable) {
            $this->loadPackages();
        }
    }

    protected function loadPackages(): void
    {
        $teamId = $this->teamId();
        if (!$teamId) {
            return;
        }

        $this->moduleKey = session('current_module_key');
        $service = app(self::SERVICE);

        $this->packageOptions = $service->packagesForTeam($teamId)
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->values()
            ->all();

        $matched = $service->resolvePackageByKey($teamId, $this->moduleKey);

        if ($matched) {
            $this->packageId = $matched->id;
            $this->autoResolved = true;
        } elseif (count($this->packageOptions) === 1) {
            $this->packageId = $this->packageOptions[0]['id'];
        }
    }

    public function changePackage(): void
    {
        $this->autoResolved = false;
        $this->packageId = null;
    }

    public function submit(): void
    {
        if (!$this->devAvailable) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Dev-Modul ist nicht verfügbar.']);
            return;
        }

        $this->validate([
            'title' => 'required|string|max:300',
            'description' => 'nullable|string|max:10000',
            'priority' => 'required|in:low,normal,high',
            'packageId' => 'required|integer',
        ], [], [
            'title' => 'Titel',
            'packageId' => 'Package',
        ]);

        $teamId = $this->teamId();
        $service = app(self::SERVICE);
        $package = $service->packagesForTeam($teamId)->firstWhere('id', $this->packageId);

        if (!$package) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Package nicht gefunden.']);
            return;
        }

        $extra = [];
        $url = null;

        if ($this->attachContext && $this->hasContext()) {
            $url = $this->contextUrl;
            $extra['context_type'] = $this->contextType;
            $extra['context_id'] = $this->contextId;
            if ($this->contextSubject) {
                $extra['context_subject'] = $this->contextSubject;
            }
            if ($this->contextSource) {
                $extra['context_source'] = $this->contextSource;
            }
        }

        $service->create($package, [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'created_by_user_id' => auth()->id(),
            'submitted_by' => auth()->user()?->name,
            'labels' => array_values(array_filter([$this->moduleKey])),
            'url' => $url,
            'extra' => $extra,
        ]);

        $this->reset(['title', 'description']);
        $this->priority = 'normal';

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Feature Request gesendet.']);
    }

    public function render()
    {
        return view('platform::livewire.terminal.feature-request');
    }
}
