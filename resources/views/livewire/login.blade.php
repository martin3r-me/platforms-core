@php(
    $policy = app()->bound(\Platform\Core\Contracts\AuthAccessPolicy::class)
        ? app(\Platform\Core\Contracts\AuthAccessPolicy::class)
        : new \Platform\Core\Services\ConfigAuthAccessPolicy()
)

<div class="d-flex justify-center items-start pt-20" style="min-height: 100vh;">
    <div class="bg-white p-6 rounded-lg shadow-md" style="width: 100%; max-width: 28rem;">
        <h2 class="text-2xl font-bold mb-6 text-center text-primary">Login</h2>

        @if($policy->isPasswordLoginAllowed())
            <form wire:submit.prevent="login" class="d-flex flex-col gap-3 mb-3">
                {{-- E-Mail --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
                    <input type="email" id="email" wire:model="email" class="form-control border border-gray-300 rounded w-full px-3 py-2" required />
                    @error('email') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Passwort --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
                    <input type="password" id="password" wire:model="password" class="form-control border border-gray-300 rounded w-full px-3 py-2" required />
                    @error('password') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="w-full bg-primary text-white py-2 px-4 rounded hover:bg-primary/90 transition">
                    Einloggen
                </button>
            </form>
        @endif

        {{-- Separator nur zeigen, wenn Passwort-Login erlaubt ist --}}
        @if($policy->isPasswordLoginAllowed())
            <div class="text-center text-secondary my-3"><small>oder</small></div>
        @endif

        {{-- SSO Button --}}
        <div class="d-grid gap-2">
            <a href="{{ route('azure-sso.login') }}"
               class="w-full border border-primary text-primary py-2 px-4 rounded hover:bg-primary/10 transition text-center">
                Mit Microsoft anmelden
            </a>
        </div>

        @if($policy->isManualRegistrationAllowed())
            <div class="mt-4 text-sm text-center text-secondary">
                Noch kein Konto?
                <a href="{{ route('register') }}" class="text-primary hover:underline">Registrieren</a>
            </div>
        @endif
    </div>
</div>