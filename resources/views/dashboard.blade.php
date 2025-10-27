<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>

<body>
    @if ($user)
        <h1>Welcome, {{ $user->name }}</h1>
        <p>Your role: {{ ucfirst($user->role) }}</p>
    @else
        <p>No user found. Please <a href="/login">login</a>.</p>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>


</body>

</html>
