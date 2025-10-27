<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'My Website')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body>
    {{-- Navbar --}}
    @include('partials.topNavbar')

    {{-- Main content --}}
    <main>
        @yield('content')
    </main>

</body>

</html>
