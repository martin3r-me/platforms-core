<?php

namespace Platform\Core\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Platform\Core\PlatformCore;
use Platform\Core\Contracts\AuthAccessPolicy;

class Register extends Component
{
    public string $name = '';
    public string $lastname = '';
    public string $email = '';
    public string $username = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount()
    {
        /** @var AuthAccessPolicy $policy */
        $policy = app(AuthAccessPolicy::class);
        if (! $policy->isManualRegistrationAllowed()) {
            // Wenn SSO-only aktiv ist oder Registration global deaktiviert → wegleiten
            if ($policy->isSsoOnly()) {
                return redirect()->route('azure-sso.login');
            }
            return redirect()->route('login');
        }
    }

    public function register()
    {
        /** @var AuthAccessPolicy $policy */
        $policy = app(AuthAccessPolicy::class);
        if (! $policy->isManualRegistrationAllowed()) {
            abort(403, 'Registration disabled');
        }
        $validated = $this->validate([
            'name'                  => 'required|string|max:255',
            'lastname'              => 'nullable|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'username'              => 'nullable|string|unique:users,username',
            'password'              => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = \Platform\Core\Models\User::create([
            'name'      => $validated['name'],
            'lastname'  => $validated['lastname'],
            'email'     => $validated['email'],
            'username'  => $validated['username'],
            'password'  => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        PlatformCore::createPersonalTeamFor($user);

        return redirect()->intended('/dashboard');
    }

    public function render()
    {
        return view('platform::livewire.register')->layout('platform::layouts.guest');
    }
}