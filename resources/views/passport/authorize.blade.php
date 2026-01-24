<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Autorisierung - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-blue-600 px-6 py-4">
                <h1 class="text-xl font-semibold text-white">Autorisierungsanfrage</h1>
            </div>

            <div class="p-6">
                <p class="text-gray-700 mb-4">
                    <strong class="text-gray-900">{{ $client->name }}</strong>
                    möchte auf Ihr Konto zugreifen.
                </p>

                @if (count($scopes) > 0)
                    <div class="mb-6">
                        <p class="text-sm font-medium text-gray-700 mb-2">Diese Anwendung möchte:</p>
                        <ul class="bg-gray-50 rounded-md p-3 space-y-1">
                            @foreach ($scopes as $scope)
                                <li class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $scope->description }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex gap-3">
                    <!-- Authorize Button -->
                    <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                            Autorisieren
                        </button>
                    </form>

                    <!-- Cancel Button -->
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-md transition-colors">
                            Abbrechen
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <p class="text-center text-sm text-gray-500 mt-4">
            Die Autorisierung kann jederzeit widerrufen werden.
        </p>
    </div>
</body>
</html>
