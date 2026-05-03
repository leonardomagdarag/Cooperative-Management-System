<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <title>Test</title>
</head>

<body>

    {{-- Load the dashboard and other stuff --}}
    {{-- currentSection is synced from the URL ?section= query param via Livewire #[Url] --}}
    <livewire:dashboard />

    @livewireScripts

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
