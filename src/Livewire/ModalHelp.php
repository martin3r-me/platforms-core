<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Platform\Core\Services\HelpDiscovery;

class ModalHelp extends Component
{
    public bool $modalShow = false;
    public array $tree = [];
    public ?string $currentModule = null;
    public string $currentPage = 'index';
    public string $content = '';
    public string $title = '';
    public array $breadcrumb = [];
    public array $expandedModules = [];

    #[On('open-help')]
    public function openHelp(?string $module = null): void
    {
        $this->tree = HelpDiscovery::getTreeForUser();

        if (empty($this->tree)) {
            $this->content = '<p class="text-[var(--ui-muted)]">Keine Hilfe-Dokumentation verfügbar.</p>';
            $this->title = 'Hilfe';
            $this->breadcrumb = [];
            $this->modalShow = true;
            return;
        }

        // If module specified and exists in tree, select it
        if ($module) {
            $found = collect($this->tree)->firstWhere('key', $module);
            if ($found) {
                $this->currentModule = $module;
                $this->expandedModules = [$module];
                $this->loadPage($module, 'index');
                $this->modalShow = true;
                return;
            }
        }

        // Default: select first module
        $first = $this->tree[0];
        $this->currentModule = $first['key'];
        $this->expandedModules = [$first['key']];
        $this->loadPage($first['key'], 'index');
        $this->modalShow = true;
    }

    #[On('open-help-page')]
    public function openHelpPage(string $module, string $path = 'index'): void
    {
        if (!$this->modalShow) {
            $this->openHelp($module);
        }
        $this->loadPage($module, $path);
    }

    public function loadPage(string $module, string $path = 'index'): void
    {
        $this->currentModule = $module;
        $this->currentPage = $path;

        // Expand this module in sidebar
        if (!in_array($module, $this->expandedModules)) {
            $this->expandedModules[] = $module;
        }

        $page = HelpDiscovery::getPage($module, $path);
        $this->content = $page['html'];
        $this->title = $page['title'];
        $this->breadcrumb = $page['breadcrumb'];
    }

    public function toggleModule(string $moduleKey): void
    {
        if (in_array($moduleKey, $this->expandedModules)) {
            $this->expandedModules = array_values(array_diff($this->expandedModules, [$moduleKey]));
        } else {
            $this->expandedModules[] = $moduleKey;
        }
    }

    public function closeModal(): void
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('platform::livewire.modal-help');
    }
}
