<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Reset Password - Settlers Credit Cooperative</title>
    <style>
        /* Code input styling */
        .code-input {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            letter-spacing: 0.5rem;
        }

        .code-input::-webkit-outer-spin-button,
        .code-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .code-input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <section class="min-h-screen flex items-center justify-center px-6 py-6" x-data="{
        step: 2,
        verifying: false,
        tokenError: ''
    }">
        <!-- Centered Container -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden max-w-2xl w-full p-6 md:p-8">

            <!-- Header -->
            <div class="text-center mb-5">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-gray-100 rounded-2xl mb-3">
                    <i class="fas fa-key text-2xl text-slate-700"></i>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold text-slate-800 mb-2">Reset Password</h1>
                <p class="text-sm text-slate-600" x-show="step === 2">
                    Enter the 6-digit code sent to your email
                </p>
                <p class="text-sm text-slate-600" x-show="step === 3">
                    Create your new password
                </p>
            </div>

            <!-- Progress Indicator -->
            <div class="mb-5">
                <div class="flex items-center justify-center gap-3">
                    <!-- Step 1 - Completed -->
                    <div class="flex flex-col items-center">
                        <div
                            class="w-10 h-10 bg-emerald-700 text-white rounded-full flex items-center justify-center text-sm font-bold mb-2">
                            <i class="fas fa-check text-xs"></i>
                        </div>
                        <span class="text-xs font-medium text-emerald-700">Email</span>
                    </div>
                    <div class="w-16 h-1 bg-emerald-700"></div>

                    <!-- Step 2 - Active/Completed based on current step -->
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold mb-2"
                            :class="step === 2 ? 'bg-emerald-700 text-white' : 'bg-emerald-700 text-white'">
                            <span x-show="step === 2">2</span>
                            <i class="fas fa-check text-xs" x-show="step === 3"></i>
                        </div>
                        <span class="text-xs font-medium"
                            :class="step >= 2 ? 'text-emerald-700' : 'text-gray-500'">Verify PIN</span>
                    </div>
                    <div class="w-16 h-1" :class="step === 3 ? 'bg-emerald-700' : 'bg-gray-200'"></div>

                    <!-- Step 3 - Active when on step 3 -->
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold mb-2"
                            :class="step === 3 ? 'bg-emerald-700 text-white' : 'bg-gray-200 text-gray-500'">
                            3
                        </div>
                        <span class="text-xs" :class="step === 3 ? 'font-medium text-emerald-700' : 'text-gray-500'">New
                            Password</span>
                    </div>
                </div>
            </div>

            <!-- Success Message -->
            @if (session('success'))
                <div class="mb-4 p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg">
                    <div class="flex items-center gap-3">
                        <div class="shrink-0">
                            <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Error Message -->
            @if (session('error'))
                <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                    <div class="flex items-center gap-3">
                        <div class="shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Form -->
            <form action="{{ route('password.update') }}" method="POST" class="space-y-4"
                @submit.prevent="
                    if(step === 2) { 
                        // Verify token via AJAX
                        if($refs.tokenInput.value.length !== 6) {
                            tokenError = 'Please enter a 6-digit code';
                            return;
                        }
                        
                        verifying = true;
                        tokenError = '';
                        
                        fetch('{{ route('password.verify') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                email: '{{ $email }}',
                                token: $refs.tokenInput.value
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            verifying = false;
                            if(data.valid) {
                                step = 3;
                            } else {
                                tokenError = data.message;
                            }
                        })
                        .catch(error => {
                            verifying = false;
                            tokenError = 'An error occurred. Please try again.';
                        });
                    } else if(step === 3) { 
                        $el.submit(); 
                    }
                ">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">

                <!-- Step 2: Email Display + PIN Entry -->
                <div x-show="step === 2" x-transition>
                    <!-- Email Display (Read-only) -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-slate-400"></i>
                            </div>
                            <div
                                class="w-full pl-12 pr-4 py-3.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-700 font-medium">
                                {{ $email }}
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">
                            <i class="fas fa-info-circle mr-1"></i>Need to change? Go back and re-enter your email
                        </p>
                    </div>

                    <!-- Verification Code Input -->
                    <div>
                        <label for="token" class="block text-sm font-medium text-slate-700 mb-2">
                            6-Digit Verification Code
                        </label>
                        <div class="relative">
                            <input type="number" id="token" name="token" maxlength="6" x-ref="tokenInput"
                                class="code-input w-full px-4 py-3.5 bg-slate-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none transition-all"
                                :class="tokenError ? 'ring-2 ring-red-500' : ''" placeholder="000000" required
                                @input="tokenError = ''" />
                        </div>
                        @error('token')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p x-show="tokenError" x-text="tokenError" class="mt-2 text-sm text-red-600"></p>
                        <p class="mt-2 text-xs text-slate-500">
                            <i class="fas fa-clock mr-1"></i>Code expires in 30 minutes
                        </p>
                    </div>

                    <!-- Info Box -->
                    <div class="p-3 bg-blue-50 rounded-xl border border-blue-100 mt-4">
                        <div class="flex gap-3">
                            <div class="shrink-0">
                                <i class="fas fa-info-circle text-blue-500"></i>
                            </div>
                            <div>
                                <p class="text-sm text-blue-800 leading-relaxed">
                                    Enter the 6-digit code sent to your email to proceed to password reset.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Next Button -->
                    <button type="submit" :disabled="verifying"
                        class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-semibold py-3 px-6 rounded-lg transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="flex items-center justify-center gap-2">
                            <i class="fas fa-spinner fa-spin" x-show="verifying"></i>
                            <span x-text="verifying ? 'Verifying Code...' : 'Continue to Password Reset'"></span>
                            <i class="fas fa-arrow-right" x-show="!verifying"></i>
                        </span>
                    </button>

                    <!-- Resend Code Link -->
                    <div class="text-center pt-2">
                        <p class="text-sm text-slate-600">
                            Didn't receive the code?
                            <a href="{{ route('password.request') }}"
                                class="font-medium text-emerald-600 hover:text-emerald-700 transition-colors">
                                Resend Code
                            </a>
                        </p>
                    </div>
                </div>

                <!-- Step 3: Password Entry -->
                <div x-show="step === 3" x-transition>
                    <!-- New Password Input -->
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-2">
                            New Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-slate-400"></i>
                            </div>
                            <input type="password" id="password" name="password"
                                class="w-full pl-12 pr-12 py-3.5 bg-slate-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none transition-all @error('password') ring-2 ring-red-500 @enderror"
                                placeholder="Enter new password" :required="step === 3" />
                            <button type="button" onclick="togglePassword('password', 'toggleIcon1')"
                                class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-slate-500">
                            Minimum 8 characters required
                        </p>
                    </div>

                    <!-- Confirm Password Input -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-2">
                            Confirm New Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-slate-400"></i>
                            </div>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                class="w-full pl-12 pr-12 py-3.5 bg-slate-50 border-0 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none transition-all"
                                placeholder="Re-enter new password" :required="step === 3" />
                            <button type="button" onclick="togglePassword('password_confirmation', 'toggleIcon2')"
                                class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                        class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-semibold py-3 px-6 rounded-lg transition-all shadow-md hover:shadow-lg mt-4">
                        <span class="flex items-center justify-center gap-2">
                            <i class="fas fa-check"></i>
                            Reset Password
                        </span>
                    </button>

                    <!-- Back to PIN Entry -->
                    <div class="text-center pt-2">
                        <button type="button" @click="step = 2"
                            class="inline-flex items-center gap-2 text-slate-600 hover:text-emerald-600 transition-colors group text-sm font-medium">
                            <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
                            Back to PIN Entry
                        </button>
                    </div>
                </div>

                <!-- Back to Sign In (Visible on both steps) -->
                <div class="text-center pt-2 border-t border-slate-100 mt-4">
                    <a href="{{ route('login_page') }}"
                        class="inline-flex items-center gap-2 text-slate-600 hover:text-emerald-600 transition-colors group text-sm font-medium mt-3">
                        <i class="fas fa-sign-in-alt text-sm"></i>
                        Back to Sign In
                    </a>
                </div>
            </form>
        </div>
    </section>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-format code input (limit to 6 digits)
        document.addEventListener('DOMContentLoaded', function() {
            const tokenInput = document.getElementById('token');
            if (tokenInput) {
                tokenInput.addEventListener('input', function(e) {
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }
                });
            }
        });
    </script>
</body>

</html>
