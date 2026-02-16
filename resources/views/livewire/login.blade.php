@php(
    $policy = app()->bound(\Platform\Core\Contracts\AuthAccessPolicy::class)
        ? app(\Platform\Core\Contracts\AuthAccessPolicy::class)
        : new \Platform\Core\Services\ConfigAuthAccessPolicy()
)

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
  <!-- Header -->
  <header class="absolute inset-x-0 top-0 z-50">
    <nav aria-label="Global" class="flex items-center justify-between p-6 lg:px-8">
      <div class="flex lg:flex-1">
        <a href="{{ route('landing') }}" class="-m-1.5 p-1.5">
          <span class="sr-only">Plattform</span>
          <img src="/logo.png" alt="Plattform" class="h-8 w-auto rounded-lg shadow object-contain" />
        </a>
      </div>
      <div class="hidden lg:flex lg:flex-1 lg:justify-end">
        <a href="{{ route('landing') }}" class="text-sm/6 font-semibold text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors">Zurück zur Startseite <span aria-hidden="true">&larr;</span></a>
      </div>
    </nav>
  </header>

  <main class="flex min-h-screen items-center justify-center px-6 py-12 sm:px-6 lg:px-8">
    <div class="w-full max-w-md">
      <!-- Logo und Titel -->
      <div class="text-center mb-8">
        <div class="mx-auto h-12 w-12 rounded-xl p-2.5 shadow-lg" style="background-color: var(--ui-primary, #6b7280);">
          <img src="/logo.png" alt="Plattform" class="h-7 w-7 rounded-lg object-contain" />
        </div>
        <h1 class="mt-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Willkommen zurück</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Melden Sie sich in Ihrem Konto an</p>
      </div>

      <!-- Formular-Karte -->
      <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm py-8 px-6 shadow-2xl rounded-2xl ring-1 ring-gray-200/50 dark:ring-gray-700/50 border border-gray-100/50 dark:border-gray-700/50">
            @if($policy->isPasswordLoginAllowed())
              <form wire:submit.prevent="login" class="space-y-6">
                {{-- E-Mail --}}
                <div>
                  <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    E-Mail-Adresse
                  </label>
                  <div class="mt-1">
                    <input type="email" id="email" wire:model="email" required autocomplete="email"
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none dark:bg-gray-700 dark:text-white sm:text-sm"
                           style="--tw-ring-color: var(--ui-primary, #6b7280);"
                           onfocus="this.style.borderColor='var(--ui-primary, #6b7280)';this.style.boxShadow='0 0 0 1px var(--ui-primary, #6b7280)'"
                           onblur="this.style.borderColor='';this.style.boxShadow=''" />
                    @error('email')
                      <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                  </div>
                </div>

                {{-- Passwort --}}
                <div>
                  <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Passwort
                  </label>
                  <div class="mt-1">
                    <input type="password" id="password" wire:model="password" required autocomplete="current-password"
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none dark:bg-gray-700 dark:text-white sm:text-sm"
                           style="--tw-ring-color: var(--ui-primary, #6b7280);"
                           onfocus="this.style.borderColor='var(--ui-primary, #6b7280)';this.style.boxShadow='0 0 0 1px var(--ui-primary, #6b7280)'"
                           onblur="this.style.borderColor='';this.style.boxShadow=''" />
                    @error('password')
                      <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                  </div>
                </div>

                <div>
                  <button type="submit"
                          class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-all duration-200 transform hover:scale-[1.02]"
                          style="background-color: var(--ui-primary, #6b7280); --tw-ring-color: var(--ui-primary, #6b7280);"
                          onmouseover="this.style.filter='brightness(0.9)'"
                          onmouseout="this.style.filter=''">
                    Anmelden
                  </button>
                </div>
              </form>

              {{-- Separator nur zeigen, wenn Passwort-Login erlaubt ist --}}
              <div class="mt-6">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200 dark:border-gray-600" />
                  </div>
                  <div class="relative flex justify-center text-sm">
                    <span class="px-3 bg-white/80 dark:bg-gray-800/80 text-gray-500 dark:text-gray-400 font-medium">Oder</span>
                  </div>
                </div>
              </div>
            @endif

            {{-- SSO Button --}}
            <div class="mt-6">
              <a href="{{ route('azure-sso.login') }}"
                 class="w-full inline-flex justify-center py-3 px-4 border border-gray-200 dark:border-gray-600 rounded-xl shadow-sm bg-white/50 dark:bg-gray-700/50 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-600 hover:shadow-md transition-all duration-200 backdrop-blur-sm">
                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M11.4 24H0V12.6h11.4V24zM24 24H12.6V12.6H24V24zM11.4 11.4H0V0h11.4v11.4zM24 11.4H12.6V0H24v11.4z"/>
                </svg>
                Mit Microsoft anmelden
              </a>
            </div>

            @if($policy->isManualRegistrationAllowed())
              <div class="mt-8 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Noch kein Konto?
                  <a href="{{ route('register') }}" class="font-semibold transition-colors" style="color: var(--ui-primary, #6b7280);"
                     onmouseover="this.style.filter='brightness(0.8)'"
                     onmouseout="this.style.filter=''">
                    Registrieren
                  </a>
                </p>
              </div>
            @endif
          </div>
        </div>
      </div>
    </main>
  </div>