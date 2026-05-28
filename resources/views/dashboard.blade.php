<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Set livewireScriptConfig BEFORE Vite loads livewire.esm so the module doesn't auto-start via DOMContentLoaded --}}
    <script>
        window.livewireScriptConfig = {
            uri: "{{ url('/livewire/update') }}",
            csrf: "{{ csrf_token() }}"
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <title>Test</title>
</head>

<body>

    {{-- Load the dashboard and other stuff --}}
    {{-- currentSection is synced from the URL ?section= query param via Livewire #[Url] --}}
    <livewire:dashboard />

    {{-- Livewire + Alpine are loaded via the Vite bundle (livewire.esm) — @livewireScripts removed to prevent double Alpine initialization --}}

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status === 419 || status === 401) {
                        preventDefault();
                        window.location.href = '{{ route('login_page') }}';
                    }
                });
            });
        });
    </script>
</body>

</html>
