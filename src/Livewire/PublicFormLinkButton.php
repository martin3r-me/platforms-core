<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Database\Eloquent\Model;
use Platform\Core\Models\CorePublicFormLink;

class PublicFormLinkButton extends Component
{
    public ?int $modelId = null;
    public ?string $modelClass = null;

    public ?string $linkUrl = null;
    public bool $isActive = false;
    public bool $hasLink = false;

    public function mount(Model $model): void
    {
        $this->modelId = $model->id;
        $this->modelClass = get_class($model);
        $this->loadLink();
    }

    private function getModel(): ?Model
    {
        if (!$this->modelId || !$this->modelClass) {
            return null;
        }
        return ($this->modelClass)::find($this->modelId);
    }

    private function loadLink(): void
    {
        $model = $this->getModel();
        if (!$model || !method_exists($model, 'publicFormLink')) {
            return;
        }

        $link = $model->publicFormLink;
        if ($link) {
            $this->hasLink = true;
            $this->linkUrl = $link->getUrl();
            $this->isActive = $link->is_active;
        } else {
            $this->hasLink = false;
            $this->linkUrl = null;
            $this->isActive = false;
        }
    }

    public function createLink(): void
    {
        $model = $this->getModel();
        if (!$model || !method_exists($model, 'getOrCreatePublicFormLink')) {
            return;
        }

        $link = $model->getOrCreatePublicFormLink();
        $this->hasLink = true;
        $this->linkUrl = $link->getUrl();
        $this->isActive = $link->is_active;
    }

    public function toggleActive(): void
    {
        $model = $this->getModel();
        if (!$model || !method_exists($model, 'publicFormLink')) {
            return;
        }

        $link = $model->publicFormLink;
        if (!$link) return;

        $link->update(['is_active' => !$link->is_active]);
        $this->isActive = $link->is_active;
    }

    public function render()
    {
        return view('platform::livewire.public-form-link-button');
    }
}
