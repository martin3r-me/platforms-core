@php(
    $policy = app()->bound(\Platform\Core\Contracts\AuthAccessPolicy::class)
        ? app(\Platform\Core\Contracts\AuthAccessPolicy::class)
        : new \Platform\Core\Services\ConfigAuthAccessPolicy()
)

<div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="flex justify-center">
            <img src="/logo.png" alt="Martin3r" class="h-12 w-auto rounded-lg shadow object-contain" />
        </div>
        <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
            Erstellen Sie Ihr Konto
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
            Oder
            <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                melden Sie sich mit Ihrem bestehenden Konto an
            </a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white dark:bg-gray-800 py-8 px-4 shadow sm:rounded-lg sm:px-10">
            @if(! $policy->isManualRegistrationAllowed())
                <div class="mb-6 rounded-md bg-yellow-50 dark:bg-yellow-900/20 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                Manuelle Registrierung deaktiviert
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                <p>Die manuelle Registrierung ist derzeit nicht verfügbar.</p>
                            </div>
                            @if($policy->isSsoOnly())
                                <div class="mt-4">
                                    <a href="{{ route('azure-sso.login') }}" 
                                       class="w-full inline-flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Mit Microsoft anmelden
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if($policy->isManualRegistrationAllowed())
                <form wire:submit.prevent="register" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        {{-- Vorname --}}
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Vorname
                            </label>
                            <div class="mt-1">
                                <input wire:model="name" id="name" name="name" type="text" required autocomplete="given-name"
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm" />
                                @error('name') 
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> 
                                @enderror
                            </div>
                        </div>

                        {{-- Nachname --}}
                        <div>
                            <label for="lastname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Nachname
                            </label>
                            <div class="mt-1">
                                <input wire:model="lastname" id="lastname" name="lastname" type="text" autocomplete="family-name"
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm" />
                                @error('lastname') 
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> 
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- E-Mail --}}
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            E-Mail-Adresse
                        </label>
                        <div class="mt-1">
                            <input wire:model="email" id="email" name="email" type="email" required autocomplete="email"
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm" />
                            @error('email') 
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> 
                            @enderror
                        </div>
                    </div>

                    {{-- Benutzername --}}
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Benutzername
                        </label>
                        <div class="mt-1">
                            <input wire:model="username" id="username" name="username" type="text" autocomplete="username"
                                   class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm" />
                            @error('username') 
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> 
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        {{-- Passwort --}}
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Passwort
                            </label>
                            <div class="mt-1">
                                <input wire:model="password" id="password" name="password" type="password" required autocomplete="new-password"
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm" />
                                @error('password') 
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> 
                                @enderror
                            </div>
                        </div>

                        {{-- Passwort bestätigen --}}
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Passwort bestätigen
                            </label>
                            <div class="mt-1">
                                <input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                                       class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm" />
                                @error('password_confirmation') 
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> 
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            Konto erstellen
                        </button>
                    </div>
                </form>
            @endif

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Bereits registriert?
                    <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                        Anmelden
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>