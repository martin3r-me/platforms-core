<div class="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6 text-center text-primary">Registrieren</h2>

    <div>

        {{-- Vorname --}}
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Vorname</label>
            <input wire:model="name" id="name" name="name" type="text"
                   class="form-control border border-gray-300 rounded w-full px-3 py-2" required>
            @error('name') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Nachname --}}
        <div>
            <label for="lastname" class="block text-sm font-medium text-gray-700 mb-1">Nachname</label>
            <input wire:model="lastname" id="lastname" name="lastname" type="text"
                   class="form-control border border-gray-300 rounded w-full px-3 py-2">
            @error('lastname') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- E-Mail --}}
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
            <input wire:model="email" id="email" name="email" type="email"
                   class="form-control border border-gray-300 rounded w-full px-3 py-2" required>
            @error('email') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Benutzername --}}
        <div>
            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Benutzername</label>
            <input wire:model="username" id="username" name="username" type="text"
                   class="form-control border border-gray-300 rounded w-full px-3 py-2">
            @error('username') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Passwort --}}
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
            <input wire:model="password" id="password" name="password" type="password"
                   class="form-control border border-gray-300 rounded w-full px-3 py-2" required>
            @error('password') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Passwort bestätigen --}}
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Passwort bestätigen</label>
            <input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation" type="password"
                   class="form-control border border-gray-300 rounded w-full px-3 py-2" required>
            @error('password_confirmation') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Submit --}}
        <button wire:click = "register"
                class="w-full bg-primary text-white py-2 px-4 rounded hover:bg-primary/90 transition">
            Registrieren
        </button>
    </div>

    <div class="mt-4 text-sm text-center text-secondary">
        Bereits registriert?
        <a href="{{ route('login') }}" class="text-primary hover:underline">Login</a>
    </div>
</div>