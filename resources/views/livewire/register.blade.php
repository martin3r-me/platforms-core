@php(
    $policy = app()->bound(\Platform\Core\Contracts\AuthAccessPolicy::class)
        ? app(\Platform\Core\Contracts\AuthAccessPolicy::class)
        : new \Platform\Core\Services\ConfigAuthAccessPolicy()
)

<div class="flex justify-center items-start pt-20 min-h-screen">
    <div class="bg-white p-6 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-[var(--ui-primary)]">Registrieren</h2>

        @if(! $policy->isManualRegistrationAllowed())
            <div class="mb-4 rounded border border-[var(--ui-border)] bg-[var(--ui-warning-10)] p-3 text-sm">
                Die manuelle Registrierung ist deaktiviert.
                @if($policy->isSsoOnly())
                    <div class="mt-3">
                        <a href="{{ route('azure-sso.login') }}" class="block w-full border border-[rgb(var(--ui-primary-rgb))] text-[var(--ui-primary)] py-2 px-4 rounded hover:bg-[rgb(var(--ui-primary-rgb))] hover:text-[var(--ui-on-primary)] transition text-center">Mit Microsoft anmelden</a>
                    </div>
                @endif
            </div>
        @endif

        @if($policy->isManualRegistrationAllowed())
        <form wire:submit.prevent="register" class="flex flex-col gap-3">
            {{-- Vorname --}}
            <div>
                <label for="name" class="block text-sm font-medium text-[var(--ui-body-color)] mb-1">Vorname</label>
                <input wire:model="name" id="name" name="name" type="text" required
                       class="block w-full rounded-md bg-white text-[var(--ui-body-color)] placeholder-gray-400 border border-[var(--ui-border)] h-10 px-3 focus:outline-none focus:ring-2 focus:ring-[rgba(var(--ui-primary-rgb),0.2)] focus:border-[rgb(var(--ui-primary-rgb))]">
                @error('name') <p class="text-sm text-[var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Nachname --}}
            <div>
                <label for="lastname" class="block text-sm font-medium text-[var(--ui-body-color)] mb-1">Nachname</label>
                <input wire:model="lastname" id="lastname" name="lastname" type="text"
                       class="block w-full rounded-md bg-white text-[var(--ui-body-color)] placeholder-gray-400 border border-[var(--ui-border)] h-10 px-3 focus:outline-none focus:ring-2 focus:ring-[rgba(var(--ui-primary-rgb),0.2)] focus:border-[rgb(var(--ui-primary-rgb))]">
                @error('lastname') <p class="text-sm text-[var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- E-Mail --}}
            <div>
                <label for="email" class="block text-sm font-medium text-[var(--ui-body-color)] mb-1">E-Mail</label>
                <input wire:model="email" id="email" name="email" type="email" required
                       class="block w-full rounded-md bg-white text-[var(--ui-body-color)] placeholder-gray-400 border border-[var(--ui-border)] h-10 px-3 focus:outline-none focus:ring-2 focus:ring-[rgba(var(--ui-primary-rgb),0.2)] focus:border-[rgb(var(--ui-primary-rgb))]">
                @error('email') <p class="text-sm text-[var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Benutzername --}}
            <div>
                <label for="username" class="block text-sm font-medium text-[var(--ui-body-color)] mb-1">Benutzername</label>
                <input wire:model="username" id="username" name="username" type="text"
                       class="block w-full rounded-md bg-white text-[var(--ui-body-color)] placeholder-gray-400 border border-[var(--ui-border)] h-10 px-3 focus:outline-none focus:ring-2 focus:ring-[rgba(var(--ui-primary-rgb),0.2)] focus:border-[rgb(var(--ui-primary-rgb))]">
                @error('username') <p class="text-sm text-[var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Passwort --}}
            <div>
                <label for="password" class="block text-sm font-medium text-[var(--ui-body-color)] mb-1">Passwort</label>
                <input wire:model="password" id="password" name="password" type="password" required
                       class="block w-full rounded-md bg-white text-[var(--ui-body-color)] placeholder-gray-400 border border-[var(--ui-border)] h-10 px-3 focus:outline-none focus:ring-2 focus:ring-[rgba(var(--ui-primary-rgb),0.2)] focus:border-[rgb(var(--ui-primary-rgb))]">
                @error('password') <p class="text-sm text-[var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Passwort bestätigen --}}
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-[var(--ui-body-color)] mb-1">Passwort bestätigen</label>
                <input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation" type="password" required
                       class="block w-full rounded-md bg-white text-[var(--ui-body-color)] placeholder-gray-400 border border-[var(--ui-border)] h-10 px-3 focus:outline-none focus:ring-2 focus:ring-[rgba(var(--ui-primary-rgb),0.2)] focus:border-[rgb(var(--ui-primary-rgb))]">
                @error('password_confirmation') <p class="text-sm text-[var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Submit --}}
            <button type="submit"
                    class="w-full bg-[rgb(var(--ui-primary-rgb))] text-[var(--ui-on-primary)] py-2 px-4 rounded hover:opacity-90 transition">
                Registrieren
            </button>
        </form>
        @endif

        <div class="mt-4 text-sm text-center text-[var(--ui-secondary)]">
            Bereits registriert?
            <a href="{{ route('login') }}" class="text-[var(--ui-primary)] hover:underline">Login</a>
        </div>
    </div>
</div>