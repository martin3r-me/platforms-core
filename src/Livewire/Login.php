<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Platform\Core\Contracts\AuthAccessPolicy;

class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function mount()
    {
        
    }

    public function login()
    {
        /** @var AuthAccessPolicy $policy */
        $policy = app(AuthAccessPolicy::class);
        if (! $policy->isPasswordLoginAllowed()) {
            abort(403, 'Password login disabled');
        }

        $credentials = $this->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $this->remember)) {
            session()->regenerate();

            return redirect()->intended('/dashboard');
        }

        $this->addError('email', 'Die Anmeldedaten sind ungültig.');
    }

    public function render()
    {
        return view('core::livewire.login')->layout('core::layouts.guest');
    }
}