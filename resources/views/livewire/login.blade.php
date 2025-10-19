@php(
    $policy = app()->bound(\Platform\Core\Contracts\AuthAccessPolicy::class)
        ? app(\Platform\Core\Contracts\AuthAccessPolicy::class)
        : new \Platform\Core\Services\ConfigAuthAccessPolicy()
)

<div class="bg-white dark:bg-gray-900 min-h-screen">
  <!-- Header -->
  <header class="absolute inset-x-0 top-0 z-50">
    <nav aria-label="Global" class="flex items-center justify-between p-6 lg:px-8">
      <div class="flex lg:flex-1">
        <a href="{{ route('landing') }}" class="-m-1.5 p-1.5">
          <span class="sr-only">Martin3r</span>
          <img src="/logo.png" alt="Martin3r" class="h-8 w-auto rounded-lg shadow object-contain" />
        </a>
      </div>
      <div class="hidden lg:flex lg:flex-1 lg:justify-end">
        <a href="{{ route('landing') }}" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Zurück zur Startseite <span aria-hidden="true">&larr;</span></a>
      </div>
    </nav>
  </header>

  <main>
    <!-- Hero section -->
    <div class="relative isolate pt-14">
      <svg aria-hidden="true" class="absolute inset-0 -z-10 size-full mask-[radial-gradient(100%_100%_at_top_right,white,transparent)] stroke-gray-200 dark:stroke-white/10">
        <defs>
          <pattern id="83fd4e5a-9d52-42fc-97b6-718e5d7ee527" width="200" height="200" x="50%" y="-1" patternUnits="userSpaceOnUse">
            <path d="M100 200V.5M.5 .5H200" fill="none" />
          </pattern>
        </defs>
        <svg x="50%" y="-1" class="overflow-visible fill-gray-50 dark:fill-gray-800/50">
          <path d="M-100.5 0h201v201h-201Z M699.5 0h201v201h-201Z M499.5 400h201v201h-201Z M-300.5 600h201v201h-201Z" stroke-width="0" />
        </svg>
        <rect width="100%" height="100%" fill="url(#83fd4e5a-9d52-42fc-97b6-718e5d7ee527)" stroke-width="0" />
      </svg>
      <div class="mx-auto max-w-7xl px-6 py-24 sm:py-32 lg:flex lg:items-center lg:gap-x-10 lg:px-8 lg:py-40">
        <div class="mx-auto max-w-2xl lg:mx-0 lg:flex-auto">
          <h1 class="mt-10 text-5xl font-semibold tracking-tight text-pretty text-gray-900 sm:text-7xl dark:text-white">Willkommen zurück</h1>
          <p class="mt-8 text-lg font-medium text-pretty text-gray-500 sm:text-xl/8 dark:text-gray-400">Melden Sie sich in Ihrem Martin3r-Konto an, um auf alle Funktionen zuzugreifen.</p>
        </div>
        <div class="mt-16 sm:mt-24 lg:mt-0 lg:shrink-0 lg:grow">
          <div class="bg-white dark:bg-gray-800 py-8 px-6 shadow-xl sm:rounded-2xl sm:px-10 ring-1 ring-gray-900/10 dark:ring-white/10">
            @if($policy->isPasswordLoginAllowed())
              <form wire:submit.prevent="login" class="space-y-6">
                {{-- E-Mail --}}
                <div>
                  <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    E-Mail-Adresse
                  </label>
                  <div class="mt-1">
                    <input type="email" id="email" wire:model="email" required autocomplete="email"
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm" />
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
                           class="appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white sm:text-sm" />
                    @error('password') 
                      <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> 
                    @enderror
                  </div>
                </div>

                <div>
                  <button type="submit"
                          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                    Anmelden
                  </button>
                </div>
              </form>

              {{-- Separator nur zeigen, wenn Passwort-Login erlaubt ist --}}
              <div class="mt-6">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300 dark:border-gray-600" />
                  </div>
                  <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400">Oder</span>
                  </div>
                </div>
              </div>
            @endif

            {{-- SSO Button --}}
            <div class="mt-6">
              <a href="{{ route('azure-sso.login') }}"
                 class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M11.4 24H0V12.6h11.4V24zM24 24H12.6V12.6H24V24zM11.4 11.4H0V0h11.4v11.4zM24 11.4H12.6V0H24v11.4z"/>
                </svg>
                Mit Microsoft anmelden
              </a>
            </div>

            @if($policy->isManualRegistrationAllowed())
              <div class="mt-6 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Noch kein Konto?
                  <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                    Registrieren
                  </a>
                </p>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </main>
</div>