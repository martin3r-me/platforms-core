@php(
    $policy = app()->bound(\Platform\Core\Contracts\AuthAccessPolicy::class)
        ? app(\Platform\Core\Contracts\AuthAccessPolicy::class)
        : new \Platform\Core\Services\ConfigAuthAccessPolicy()
)

<div class="container-fluid vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="card shadow" style="max-width: 480px; width: 100%;">
        <div class="card-body">
            <h2 class="h4 mb-4 text-center">Login</h2>

            @if($policy->isPasswordLoginAllowed())
                <form wire:submit.prevent="login" class="mb-3">
                    {{-- E-Mail --}}
                    <div class="mb-3">
                        <label for="email" class="form-label">E-Mail</label>
                        <input type="email" id="email" wire:model="email" class="form-control" required />
                        @error('email') <div class="form-text text-danger">{{ $message }}</div> @enderror
                    </div>

                    {{-- Passwort --}}
                    <div class="mb-3">
                        <label for="password" class="form-label">Passwort</label>
                        <input type="password" id="password" wire:model="password" class="form-control" required />
                        @error('password') <div class="form-text text-danger">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Einloggen</button>
                    </div>
                </form>
            @endif

            {{-- Separator nur zeigen, wenn beides angeboten wird --}}
            @if($policy->isPasswordLoginAllowed())
                <div class="text-center text-muted my-3"><small>oder</small></div>
            @endif

            {{-- SSO Button --}}
            <div class="d-grid gap-2">
                <a href="{{ route('azure-sso.login') }}" class="btn btn-outline-primary">
                    @svg('heroicons.microsoft')
                    Mit Microsoft anmelden
                </a>
            </div>

            @if($policy->isManualRegistrationAllowed())
                <div class="mt-3 text-center">
                    <small>Noch kein Konto? <a href="{{ route('register') }}">Registrieren</a></small>
                </div>
            @endif
        </div>
    </div>
</div>