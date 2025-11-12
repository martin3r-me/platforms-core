<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Platform\Core\Models\TeamBillableUsage;
use Platform\Core\Models\TeamUserLastModule;
use Platform\Core\Models\Module;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Dashboard extends Component
{
    public $modules;
    public $currentTeam;
    public $teamMembers = [];
    public $monthlyTotal = 0;

    public function mount()
    {
        $this->modules = PlatformCore::getModules();
        
        if (Auth::check()) {
            $user = Auth::user();
            $this->currentTeam = $user->currentTeam;
            
            if ($this->currentTeam) {
                $this->teamMembers = $this->currentTeam->users()->get()->all();
                $this->loadMonthlyCosts();
                
                // Prüfe ob es ein zuletzt verwendetes Modul gibt und redirecte dorthin
                $lastModuleKey = TeamUserLastModule::getLastModule($user->id, $this->currentTeam->id);
                
                Log::info('Dashboard: Prüfe zuletzt verwendetes Modul', [
                    'user_id' => $user->id,
                    'team_id' => $this->currentTeam->id,
                    'last_module_key' => $lastModuleKey,
                ]);
                
                if ($lastModuleKey && $lastModuleKey !== 'dashboard') {
                    $moduleModel = Module::where('key', $lastModuleKey)->first();
                    if ($moduleModel) {
                        $teamAllowed = $this->currentTeam
                            ->modules()
                            ->where('module_id', $moduleModel->id)
                            ->wherePivot('enabled', true)
                            ->exists();

                        Log::info('Dashboard: Modul-Prüfung', [
                            'module_key' => $lastModuleKey,
                            'team_allowed' => $teamAllowed,
                            'team_id' => $this->currentTeam->id,
                        ]);

                        if ($teamAllowed) {
                            // Zum zuletzt verwendeten Modul redirecten
                            Log::info('Dashboard: Redirect zum Modul', [
                                'module_key' => $lastModuleKey,
                                'redirect_to' => '/' . $lastModuleKey,
                            ]);
                            // Livewire v3: $this->redirect() verwenden
                            $this->redirect('/' . $lastModuleKey, navigate: false);
                            return;
                        }
                    } else {
                        Log::warning('Dashboard: Modul nicht gefunden', [
                            'module_key' => $lastModuleKey,
                        ]);
                    }
                } else {
                    Log::info('Dashboard: Kein zuletzt verwendetes Modul oder Dashboard', [
                        'last_module_key' => $lastModuleKey,
                    ]);
                }
            }
        }
    }

    protected function loadMonthlyCosts()
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        
        $sum = TeamBillableUsage::where('team_id', $this->currentTeam->id)
            ->whereBetween('usage_date', [$startOfMonth, $endOfMonth])
            ->sum('total_cost');
            
        // Sicherstellen, dass es ein Float ist
        if (is_numeric($sum)) {
            $this->monthlyTotal = (float) $sum;
        } else {
            $this->monthlyTotal = 0.0;
        }
    }

    public function render()
    {
        return view('platform::livewire.dashboard')->layout('platform::layouts.app');
    }
}