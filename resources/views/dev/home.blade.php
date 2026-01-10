<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Home</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-white min-h-screen flex items-center justify-center">
    <div class="max-w-2xl w-full px-6">
        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Developer Portal</h1>
            <p class="text-gray-600">Congrats on getting here... Wait, who are you?</p>
        </div>

        {{-- Main Buttons --}}
        <div class="space-y-4">
            {{-- Dev Panel Button --}}
            <a href="{{ route('dev.panel') }}" class="block border border-gray-300 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-2">Dev Panel</h2>
                </a>

            {{-- Test Dashboard Button --}}
            <a href="{{ route('dev.test-dashboard') }}" class="block border border-gray-300 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-2">Test Dashboard</h2>
            </a>
        </div>

        {{-- Logout Button --}}
        <div class="mt-8">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-gray-600 underline">Logout</button>
            </form>
        </div>
    </div>
</body>

</html>
