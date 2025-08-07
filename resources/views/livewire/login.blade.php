<div class="d-flex justify-center items-center min-h-screen bg-gray-50">
    <div class="bg-white p-6 rounded shadow max-w-md w-full">
        <h2 class="text-2xl font-bold mb-6 text-center text-primary">Login</h2>

        <form wire:submit.prevent="login" class="d-flex flex-col gap-y-4">
            {{-- E-Mail --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
                <input type="email" id="email" wire:model="email" class="form-control w-full border rounded px-3 py-2" required />
                @error('email') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Passwort --}}
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
                <input type="password" id="password" wire:model="password" class="form-control w-full border rounded px-3 py-2" required />
                @error('password') <p class="text-sm text-danger mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Button --}}
            <div>
                <button type="submit" class="w-full bg-primary text-white py-2 rounded hover:bg-primary/90 transition">
                    Einloggen
                </button>
            </div>
        </form>

        <div class="mt-4 text-sm text-center text-secondary">
            Noch kein Konto? <a href="{{ route('register') }}" class="text-primary hover:underline">Registrieren</a>
        </div>
    </div>
</div>