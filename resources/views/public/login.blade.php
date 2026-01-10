<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/login.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <title>Login - Settlers Credit Cooperative</title>
</head>
</head>

<body class="bg-white md:bg-linear-to-br md:from-slate-400 md:to-slate-200">
    <section class="min-h-screen flex items-start md:items-center justify-center px-6 py-4 md:p-8">
        <!-- Login Container -->
        <div
            class="md:bg-white md:rounded-3xl md:shadow-2xl overflow-hidden md:max-w-5xl w-full flex flex-col md:flex-row">
            <!-- Image Section -->
            <div
                class="hidden md:flex md:w-3/5 p-8 md:p-12 items-center justify-center relative overflow-hidden rounded-l-3xl">
                <!-- Background Image -->
                <img src="{{ asset('images/login_img.jpg') }}" alt="Business background"
                    class="absolute inset-0 w-full h-full object-cover">
            </div>

            <!-- Login Form Section -->
            <div class="md:w-2/5 p-0 md:p-16 md:rounded-r-3xl" id="loginContainer">
                <div class="max-w-md mx-auto">
                    <!-- Back Button (Mobile Only) -->
                    <div class="md:hidden mb-6">
                        <a href="{{ route('home_page') }}"
                            class="inline-flex items-center text-slate-600 hover:text-slate-900 transition-colors">
                            <i class="fas fa-chevron-left text-lg"></i>
                        </a>
                    </div>

                    <!-- Header -->
                    <div class="mb-8">
                        <h1 class="text-2xl md:text-4xl font-bold text-slate-800 mb-2">Sign In</h1>
                        <p class="text-sm md:text-base text-slate-600">
                            Enter your credentials to access your account
                        </p>
                    </div>

                    <!-- Login Form -->
                    <form class="space-y-4" id="loginForm" action="{{ route('login') }}" method="POST">
                        @csrf
                        <!-- Email Input -->
                        <div>
                            <label for="email" class="block text-xs md:text-sm font-medium text-slate-500 mb-2">
                                Email
                            </label>
                            <div class="relative">
                                <input type="email" id="email" name="email"
                                    class="w-full px-4 py-3.5 bg-slate-50 border-0 rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white outline-none transition-all text-sm"
                                    placeholder="Enter your email" required />
                            </div>

                        </div>

                        <!-- Password Input -->
                        <div>
                            <label for="password" class="block text-xs md:text-sm font-medium text-slate-500 mb-2">
                                Password
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password"
                                    class="w-full px-4 py-3.5 pr-12 bg-slate-50 border-0 rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white outline-none transition-all text-sm"
                                    placeholder="Enter your password" required />
                                <button type="button" onclick="togglePassword()"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Warning Notification -->
                        <div id="warn"
                            class="hidden mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg ">
                            <div class="flex items-center gap-3">
                                <div class="shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-red-800" id="warnText">Incorrect Credentials</p>
                                </div>
                                <button type="button" onclick="closeWarn()"
                                    class="shrink-0 text-red-400 hover:text-red-600 transition-colors">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between flex-wrap gap-2 pt-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="remember"
                                    class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500 cursor-pointer" />
                                <label for="remember" class="ml-2 text-sm text-slate-600 cursor-pointer select-none">
                                    Remember me
                                </label>
                            </div>
                            <a href="#"
                                class="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                                Forgot password?
                            </a>
                        </div>



                        <!-- Login Button -->
                        <button type="submit"
                            class="w-full bg-linear-to-r from-emerald-500 to-cyan-500 hover:from-primary1 hover:to-primary2 text-white font-semibold py-4 px-6 rounded-2xl focus:ring-blue-300 transition-all transform active:scale-[0.98]">
                            Sign in
                        </button>

                        <!-- Back Button (Desktop Only) -->
                        <div class="hidden md:block text-center pt-4">
                            <a href="{{ route('home_page') }}"
                                class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors group">
                                <i
                                    class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                                <span class="font-medium">Back to Home</span>
                            </a>
                        </div>
                </div>
            </div>
            </form>
        </div>
    </section>

</body>

</html>
