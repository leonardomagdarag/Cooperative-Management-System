<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @vite('resources/css/public/login.css')
    <title>Login</title>
</head>

<body>
    <div class="login-container">
        <div class="container-left">
            <img src="https://placehold.co/300x400" alt="placeholder image">
        </div>
        <div class="container-right">
            <div class="login-header">
                <h2>Welcome Back!</h2>
                <p>Please login to your account</p>
            </div>

            <form class="login-form" method="POST" action="{{ route('login') }}">
                @csrf {{-- for security, apparenlty (idk) --}}
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="text" name="email" class="input-field" placeholder="Enter your email"
                        autocomplete="off" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" class="input-field" placeholder="Enter your password"
                        autocomplete="off" required>
                </div>
                <div class="options">
                    <section>
                        <input type="checkbox" id="remember-me">
                        <label for="remember-me">Remember Me</label>
                    </section>
                    <section>
                        <a href="#">Forgot Password?</a>
                    </section>
                </div>
                <div class="input-submit">
                    <button type="submit" class="btn-submit">Sign In</button>
                </div>

            </form>
            <a href="{{ route('home_page') }}" class="btn-return">Return</a>
        </div>
    </div>
</body>

</html>
