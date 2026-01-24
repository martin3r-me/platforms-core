<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens as SanctumHasApiTokens;
use Laravel\Passport\HasApiTokens as PassportHasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Platform\Core\Models\Module;
use Platform\Core\Models\CoreAiModel;

class User extends Authenticatable
{
    use PassportHasApiTokens, SanctumHasApiTokens {
        // Passport's Methoden haben Vorrang (für OAuth)
        PassportHasApiTokens::tokens insteadof SanctumHasApiTokens;
        PassportHasApiTokens::tokenCan insteadof SanctumHasApiTokens;
        PassportHasApiTokens::tokenCant insteadof SanctumHasApiTokens;
        // Sanctum's createToken(), currentAccessToken() und withAccessToken() haben Vorrang (wird für Bearer Tokens verwendet)
        SanctumHasApiTokens::createToken insteadof PassportHasApiTokens;
        SanctumHasApiTokens::currentAccessToken insteadof PassportHasApiTokens;
        SanctumHasApiTokens::withAccessToken insteadof PassportHasApiTokens;
        // Sanctum's Methoden als sanctum*() verfügbar machen (für explizite Verwendung)
        SanctumHasApiTokens::tokens as sanctumTokens;
        SanctumHasApiTokens::tokenCan as sanctumTokenCan;
        SanctumHasApiTokens::tokenCant as sanctumTokenCant;
        // Passport's Methoden als passport*() verfügbar machen (falls benötigt)
        PassportHasApiTokens::createToken as passportCreateToken;
        PassportHasApiTokens::currentAccessToken as passportCurrentAccessToken;
        PassportHasApiTokens::withAccessToken as passportWithAccessToken;
    }
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'core_ai_model_id',
        'instruction',
        'team_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function fullname(): Attribute
    {
        return Attribute::get(fn () => trim($this->name . ' ' . ($this->lastname ?? '')));
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Basis-Relationship für das aktuelle Team (für DB-Zugriffe).
     * Wird intern von currentTeam Attribute verwendet.
     */
    public function currentTeamRelation()
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Dynamisches currentTeam Attribute - gibt je nach Modul das richtige Team zurück.
     * 
     * - Für root-scoped Module (scope_type = 'parent'): Gibt das Root-Team zurück
     * - Für team-spezifische Module (scope_type = 'single'): Gibt das aktuelle Team zurück
     * 
     * @return Team|null
     */
    public function currentTeam(): Attribute
    {
        return Attribute::make(
            get: function () {
                $baseTeam = $this->currentTeamRelation;
                if (!$baseTeam) {
                    return null;
                }

                // Versuche Modul-Key aus aktueller Route zu extrahieren
                $moduleKey = request()->segment(1);
                
                // Wenn kein Modul-Key oder leer, verwende Basis-Team
                if (empty($moduleKey)) {
                    return $baseTeam;
                }

                // Modul finden und Scope-Type prüfen
                $module = Module::where('key', $moduleKey)->first();
                
                // Wenn Modul nicht gefunden oder team-spezifisch, verwende Basis-Team
                if (!$module || $module->isTeamScoped()) {
                    return $baseTeam;
                }

                // Root-scoped Modul: Immer Root-Team zurückgeben
                if ($module->isRootScoped()) {
                    return $baseTeam->getRootTeam();
                }

                // Fallback: Basis-Team
                return $baseTeam;
            }
        );
    }

    public function modules()
    {
        return $this->morphToMany(Module::class, 'modulable')
            ->withPivot(['role', 'enabled', 'guard', 'team_id'])
            ->withTimestamps();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Stelle sicher, dass team_id nur für AI-User gesetzt ist
        static::saving(function ($user) {
            if ($user->type !== 'ai_user') {
                $user->team_id = null;
                // Normale User müssen email und password haben - nur beim Erstellen validieren
                if (!$user->exists) {
                    if (!$user->email || !$user->password) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'email' => 'Email ist für normale User erforderlich.',
                            'password' => 'Passwort ist für normale User erforderlich.',
                        ]);
                    }
                }
            } else {
                // AI-User brauchen kein email/password, setze auf null falls leer
                if (empty($user->email)) {
                    $user->email = null;
                }
                if (empty($user->password)) {
                    $user->password = null;
                }
            }
        });

        // Validiere, dass AI-User ein team_id haben sollten - nur beim Erstellen
        static::saving(function ($user) {
            if ($user->type === 'ai_user' && !$user->exists && !$user->team_id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'team_id' => 'AI-User müssen einem Team zugeordnet sein (team_id ist erforderlich).',
                ]);
            }
        });

        // Validiere, dass AI-User einen Namen haben - nur beim Erstellen
        static::saving(function ($user) {
            if ($user->type === 'ai_user' && !$user->exists && empty($user->name)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'name' => 'Name ist für AI-User erforderlich.',
                ]);
            }
        });
    }

    /**
     * Prüft, ob der User ein AI-User ist
     */
    public function isAiUser(): bool
    {
        return $this->type === 'ai_user';
    }

    /**
     * Scope: Alle AI-User abrufen
     */
    public function scopeAiUsers($query)
    {
        return $query->where('type', 'ai_user');
    }

    /**
     * Scope: Alle normalen User abrufen
     */
    public function scopeRegularUsers($query)
    {
        return $query->where('type', '!=', 'ai_user')->orWhereNull('type');
    }

    /**
     * Beziehung zum Team (nur für AI-User relevant - das Home-Team)
     */
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Beziehung zum AI-Model
     */
    public function coreAiModel()
    {
        return $this->belongsTo(CoreAiModel::class, 'core_ai_model_id');
    }

    /**
     * Prüft, ob der AI-User einem bestimmten Team zugewiesen werden kann
     * basierend auf der Team-Hierarchie
     */
    public function canBeAssignedToTeam(Team $team): bool
    {
        // Nur für AI-User relevant
        if (!$this->isAiUser() || !$this->team_id) {
            return true; // Normale User können immer zugewiesen werden
        }

        $homeTeam = $this->team;
        if (!$homeTeam) {
            return false;
        }

        // Wenn das Ziel-Team das Home-Team ist, ist es erlaubt
        if ($team->id === $homeTeam->id) {
            return true;
        }

        // Prüfe, ob das Ziel-Team ein Kind-Team des Home-Teams ist
        return $homeTeam->isParentOf($team);
    }

    /**
     * Gibt alle Teams zurück, denen dieser AI-User zugewiesen werden kann
     */
    public function getAssignableTeams()
    {
        if (!$this->isAiUser() || !$this->team_id) {
            return Team::all(); // Normale User können allen Teams zugewiesen werden
        }

        $homeTeam = $this->team;
        if (!$homeTeam) {
            return collect([]);
        }

        // Sammle das Home-Team und alle Kind-Teams
        $assignableTeams = collect([$homeTeam]);
        $childTeams = $homeTeam->childTeams()->get();
        foreach ($childTeams as $childTeam) {
            $assignableTeams->push($childTeam);
            // Rekursiv alle Kind-Teams sammeln
            $this->collectAllChildTeams($childTeam, $assignableTeams);
        }

        return $assignableTeams;
    }

    /**
     * Rekursiv alle Kind-Teams sammeln
     */
    protected function collectAllChildTeams(Team $team, $collection)
    {
        $childTeams = $team->childTeams()->get();
        foreach ($childTeams as $childTeam) {
            $collection->push($childTeam);
            $this->collectAllChildTeams($childTeam, $collection);
        }
    }

    /**
     * Meta OAuth Token dieses Users
     */
    /**
     * Ruft die Meta IntegrationConnection für diesen User ab
     * @deprecated Verwende stattdessen MetaIntegrationService::getConnectionForUser()
     */
    public function metaConnection()
    {
        $metaService = app(\Platform\Integrations\Services\MetaIntegrationService::class);
        return $metaService->getConnectionForUser($this);
    }
    
    /**
     * @deprecated Verwende stattdessen metaConnection()
     */
    public function metaToken()
    {
        return $this->metaConnection();
    }

    /**
     * Facebook Pages dieses Users
     */
    public function facebookPages()
    {
        return $this->hasMany(\Platform\Integrations\Models\IntegrationsFacebookPage::class, 'user_id');
    }

    /**
     * Instagram Accounts dieses Users
     */
    public function instagramAccounts()
    {
        return $this->hasMany(\Platform\Integrations\Models\IntegrationsInstagramAccount::class, 'user_id');
    }

    /**
     * WhatsApp Accounts dieses Users
     */
    public function whatsappAccounts()
    {
        return $this->hasMany(\Platform\Integrations\Models\IntegrationsWhatsAppAccount::class, 'user_id');
    }
}