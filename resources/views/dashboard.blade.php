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
    <livewire:dashboard />

    @livewireScripts
</body>

</html>
