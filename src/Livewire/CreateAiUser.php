<?php

namespace Platform\Core\Livewire;

use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Models\CoreAiModel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class CreateAiUser extends Component
{
    public Team $team;
    
    public $showForm = false;
    
    public $form = [
        'name' => '',
        'core_ai_model_id' => null,
        'instruction' => '',
    ];

    protected function rules()
    {
        $rules = [
            'form.name' => 'required|string|max:255',
            'form.instruction' => 'nullable|string',
        ];

        // Prüfe ob core_ai_models Tabelle existiert
        if (Schema::hasTable('core_ai_models')) {
            $rules['form.core_ai_model_id'] = 'nullable|exists:core_ai_models,id';
        } else {
            $rules['form.core_ai_model_id'] = 'nullable|integer';
        }

        return $rules;
    }

    protected $messages = [
        'form.name.required' => 'Der Name ist erforderlich.',
        'form.core_ai_model_id.exists' => 'Das ausgewählte AI-Model existiert nicht.',
    ];

    public function mount(Team $team)
    {
        $this->team = $team;
    }

    public function createAiUser()
    {
        $this->validate();

        Gate::authorize('addTeamMember', $this->team);

        // Erstelle den AI-User
        $aiUser = User::create([
            'name' => $this->form['name'],
            'type' => 'ai_user',
            'core_ai_model_id' => $this->form['core_ai_model_id'],
            'instruction' => $this->form['instruction'] ?? null,
            'team_id' => $this->team->id,
            'email' => null,
            'password' => null,
        ]);

        // Füge den AI-User direkt zum Team hinzu
        $this->team->users()->attach($aiUser, ['role' => 'member']);

        // Reset form
        $this->reset('form', 'showForm');

        session()->flash('message', 'AI-User erfolgreich erstellt und zum Team hinzugefügt.');

        $this->dispatch('ai-user-created');
    }

    public function render()
    {
        // Hole verfügbare AI-Models falls die Tabelle existiert
        $aiModels = collect([]);
        
        if (Schema::hasTable('core_ai_models') && class_exists(CoreAiModel::class)) {
            try {
                $aiModels = CoreAiModel::all();
            } catch (\Exception $e) {
                // Fallback falls Model nicht existiert
                $aiModels = collect([]);
            }
        }

        return view('platform::livewire.create-ai-user', [
            'aiModels' => $aiModels,
        ]);
    }
}
