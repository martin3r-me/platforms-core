@php(
    $policy = app()->bound(\Platform\Core\Contracts\AuthAccessPolicy::class)
        ? app(\Platform\Core\Contracts\AuthAccessPolicy::class)
        : new \Platform\Core\Services\ConfigAuthAccessPolicy()
)

<div class="flex justify-center items-start pt-20 min-h-screen">
    <div class="bg-white p-6 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-[color:var(--ui-primary)]">Login</h2>

        @if($policy->isPasswordLoginAllowed())
            <form wire:submit.prevent="login" class="flex flex-col gap-3 mb-3">
                {{-- E-Mail --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-[color:var(--ui-body-color)] mb-1">E-Mail</label>
                    <input type="email" id="email" wire:model="email" required
                           class="block w-full rounded-md bg-white text-[color:var(--ui-body-color)] placeholder-gray-400 border border-[color:var(--ui-border)] h-10 px-3 focus:outline-none focus:ring-2 focus:ring-[color:rgba(var(--ui-primary-rgb),0.2)] focus:border-[color:rgb(var(--ui-primary-rgb))]" />
                    @error('email') <p class="text-sm text-[color:var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Passwort --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-[color:var(--ui-body-color)] mb-1">Passwort</label>
                    <input type="password" id="password" wire:model="password" required
                           class="block w-full rounded-md bg-white text-[color:var(--ui-body-color)] placeholder-gray-400 border border-[color:var(--ui-border)] h-10 px-3 focus:outline-none focus:ring-2 focus:ring-[color:rgba(var(--ui-primary-rgb),0.2)] focus:border-[color:rgb(var(--ui-primary-rgb))]" />
                    @error('password') <p class="text-sm text-[color:var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="w-full bg-[rgb(var(--ui-primary-rgb))] text-[color:var(--ui-on-primary)] py-2 px-4 rounded hover:opacity-90 transition">
                    Einloggen
                </button>
            </form>
        @endif

        {{-- Separator nur zeigen, wenn Passwort-Login erlaubt ist --}}
        @if($policy->isPasswordLoginAllowed())
            <div class="text-center text-[color:var(--ui-secondary)] my-3"><small>oder</small></div>
        @endif

        {{-- SSO Button --}}
        <div class="grid gap-2">
            <a href="{{ route('azure-sso.login') }}"
               class="w-full border border-[color:rgb(var(--ui-primary-rgb))] text-[color:var(--ui-primary)] py-2 px-4 rounded hover:bg-[rgb(var(--ui-primary-rgb))] hover:text-[color:var(--ui-on-primary)] transition text-center">
                Mit Microsoft anmelden
            </a>
        </div>

        @if($policy->isManualRegistrationAllowed())
            <div class="mt-4 text-sm text-center text-[color:var(--ui-secondary)]">
                Noch kein Konto?
                <a href="{{ route('register') }}" class="text-[color:var(--ui-primary)] hover:underline">Registrieren</a>
            </div>
        @endif
    </div>
</div>