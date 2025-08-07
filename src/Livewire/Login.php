<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

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
        return view('platform::livewire.login')->layout('platform::layouts.guest');
    }
}