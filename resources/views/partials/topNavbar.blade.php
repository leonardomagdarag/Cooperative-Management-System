@php
    function sectionLink($section)
    {
        return request()->routeIs('home_page') ? "#$section" : route('home_page') . "#$section";
    }
@endphp

<nav id="top-navbar">
    <div class="logo">
        <img src="https://placehold.co/150x40" alt="SouthVille 7 Logo">
    </div>

    <a href="{{ route('home_page') }}">Home</a>
    <a href="{{ route('about_page') }}">About Us</a>
    <a href="{{ route('events_page') }}">Events</a>
    <a href="{{ route('home_page') }}">Stories</a>
    <a href="{{ sectionLink('Contact') }}">Contact</a>
    <a class="btn-login" href="{{ route('login') }}">Login</a>
</nav>
