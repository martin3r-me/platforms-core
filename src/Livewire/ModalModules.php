<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; 

class ModalModules extends Component
{
    public $modalShow;
    public $showMatrix;
    public $modules;
    public $matrixUsers;
    public $matrixModules;
    public $userModuleMap = [];

    #[On('open-modal-modules')] 
    public function openModalModules()
    {
        $this->modalShow = true;
    }

    

    public function mount()
    {
        $this->modalShow = false;

        $user = auth()->user();
        $team = method_exists($user, 'currentTeam') ? $user->currentTeam : null;

        // Hole alle sichtbaren Module (z. B. nach Guard gefiltert)
        $modules = PlatformCore::getVisibleModules();

        // Filtere Module nach Berechtigung
        $this->modules = collect($modules)->filter(function($module) use ($user, $team) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $module['key'])->first();
            if (!$moduleModel) return false;

            $userAllowed = $user->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists();
            $teamAllowed = $team
                ? $team->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists()
                : false;

            return $userAllowed || $teamAllowed;
        })->values();

        // Matrix-Vorbereitung wie gehabt
        $teamId = $user->currentTeam?->id;
        $this->matrixUsers = $teamId
            ? \Platform\Core\Models\User::whereHas('teams', function($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            })->get()
            : collect();

        $this->matrixModules = \Platform\Core\Models\Module::all();
        $this->refreshMatrix();
    }

    public function toggleMatrix($userId, $moduleId)
    {
        // User und Modul holen
        $user = \Platform\Core\Models\User::findOrFail($userId);
        $module = \Platform\Core\Models\Module::findOrFail($moduleId);

        // Prüfen, ob dieses Modul dem User bereits zugeordnet ist
        $alreadyAssigned = $user->modules()->where('module_id', $moduleId)->exists();

        if ($alreadyAssigned) {
            // Entferne das Modul (detach) aus der Pivot-Tabelle
            $user->modules()->detach($moduleId);
        } else {
            // Hänge das Modul an (attach) – ggf. weitere Pivot-Werte möglich
            $user->modules()->attach($moduleId, [
                'role' => null,
                'enabled' => true,
                'guard' => 'web', // Optional: Den Guard dynamisch setzen, falls benötigt
            ]);
        }

        // Optional: Matrix neu laden, damit das UI sofort den Status anzeigt
        $this->refreshMatrix();
    }

    // Setzt alle Modul-IDs, die ein User hat, als Array für den schnellen Zugriff
     // [user_id => [module_id, ...], ...]

    public function refreshMatrix()
    {
        $teamId = auth()->user()->currentTeam?->id;
        $this->matrixUsers = $teamId
            ? \Platform\Core\Models\User::whereHas('teams', function($q) use ($teamId) {
                $q->where('teams.id', $teamId);
            })->get()
            : collect();

        $this->matrixModules = \Platform\Core\Models\Module::all();

        // Build map: user_id => [module_id, ...]
        $this->userModuleMap = [];
        foreach ($this->matrixUsers as $user) {
            $this->userModuleMap[$user->id] = $user->modules->pluck('id')->toArray();
        }
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('platform::livewire.modal-modules');
    }
}

