<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <title>Membership Registration - Settlers Credit Cooperative</title>
</head>

@php
    $termsContent = data_get($publicContent ?? [], 'policies.terms_and_conditions', '');
    $privacyContent = data_get($publicContent ?? [], 'policies.privacy_policy', '');
@endphp

<body class="public-page">

    <!-- Success Toast Notification -->
    @if (session('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-[-100%]"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-[-100%]"
            class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-md w-full mx-4">
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0">
                            <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check-circle text-emerald-700 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 pt-1">
                            <h3 class="text-sm font-semibold text-slate-800 mb-1">Success!</h3>
                            <p class="text-sm text-slate-600">{{ session('success') }}</p>
                        </div>
                        <button @click="show = false" type="button"
                            class="shrink-0 text-slate-400 hover:text-slate-600 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <!-- Progress bar -->
                <div class="h-1 bg-gray-200">
                    <div class="h-full bg-emerald-700 animate-shrink"></div>
                </div>
            </div>
        </div>
    @endif

    <!-- Error Alert -->
    @if ($errors->any() || session('error'))
        <div class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-md w-full mx-4">
            <div class="bg-white rounded-xl shadow-lg border border-red-200 overflow-hidden">
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0">
                            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-circle text-red-700 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1 pt-1">
                            <h3 class="text-sm font-semibold text-slate-800 mb-1">Registration Error</h3>
                            @if (session('error'))
                                <p class="text-sm text-slate-600">{{ session('error') }}</p>
                            @else
                                <p class="text-sm text-slate-600 mb-2">Please correct the following errors:</p>
                                <ul class="text-sm text-red-600 space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>• {{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <section class="min-h-screen py-12 px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <a href="{{ route('home_page') }}"
                    class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors mb-6">
                    <i class="fas fa-arrow-left"></i>
                    <span class="font-medium">Back to Home</span>
                </a>
                <div class="mb-4 flex justify-center">
                    <img src="{{ asset('images/Logo.png') }}" alt="Settlers Logo"
                        class="h-16 w-16 rounded-full object-cover shadow-sm">
                </div>
                <h1 class="text-3xl md:text-5xl font-extrabold text-slate-900 mb-2">Membership Application</h1>
                <p class="text-base text-slate-600">
                    Join Settlers Credit Cooperative and become part of our growing community
                </p>
            </div>

            <!-- Membership Requirements Card -->
            <div class="public-card p-6 mb-8">
                <div class="flex items-start gap-4 mb-4">
                    <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-info-circle text-emerald-700 text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800 mb-2">Membership Requirements</h3>
                        <div class="grid md:grid-cols-3 gap-4 text-sm text-slate-600">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-check-circle text-emerald-600"></i>
                                <span>Minimum Share Capital: <strong>₱500</strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-check-circle text-emerald-600"></i>
                                <span>Processing Fee: <strong>₱100</strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-check-circle text-emerald-600"></i>
                                <span>Initial Savings: <strong>₱500</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registration Form -->
            <form class="public-card p-8" action="{{ route('register') }}"
                method="POST" x-data="registrationForm()">
                @csrf

                <!-- Personal Information Section -->
                <div class="mb-10">
                    <h2 class="text-xl font-bold text-slate-800 mb-1 flex items-center gap-2">
                        <i class="fas fa-user text-emerald-700"></i>
                        Personal Information
                    </h2>
                    <p class="text-sm text-slate-500 mb-6">Please provide your basic personal details</p>

                    <div class="space-y-5">
                        <!-- Full Name -->
                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-slate-700 mb-2">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="first_name" name="first_name" required
                                    value="{{ old('first_name') }}"
                                    class="public-input py-2.5 text-sm @error('first_name') border-red-500 @enderror"
                                    placeholder="Juan" />
                                @error('first_name')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="middle_name" class="block text-sm font-medium text-slate-700 mb-2">
                                    Middle Name
                                </label>
                                <input type="text" id="middle_name" name="middle_name"
                                    value="{{ old('middle_name') }}"
                                    class="public-input py-2.5 text-sm"
                                    placeholder="Santos" />
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-slate-700 mb-2">
                                    Last Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="last_name" name="last_name" required
                                    value="{{ old('last_name') }}"
                                    class="public-input py-2.5 text-sm @error('last_name') border-red-500 @enderror"
                                    placeholder="Dela Cruz" />
                                @error('last_name')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Gender and Birthday -->
                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label for="gender" class="block text-sm font-medium text-slate-700 mb-2">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <select id="gender" name="gender" required
                                    class="public-input py-2.5 text-sm">
                                    <option value="">Select Gender</option>
                                    <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male
                                    </option>
                                    <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female
                                    </option>
                                    <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Prefer not
                                        to say</option>
                                </select>
                            </div>
                            <div>
                                <label for="birthdate" class="block text-sm font-medium text-slate-700 mb-2">
                                    Birthday <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="birthdate" name="birthdate" required x-model="birthdate"
                                    value="{{ old('birthdate') }}" @change="calculateAge"
                                    class="public-input py-2.5 text-sm" />
                            </div>
                            <div>
                                <label for="age" class="block text-sm font-medium text-slate-700 mb-2">
                                    Age <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="age" name="age" readonly x-model="age"
                                    class="public-input py-2.5 bg-slate-100 text-sm cursor-not-allowed"
                                    placeholder="Auto-calculated" />
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label for="phone" class="block text-sm font-medium text-slate-700 mb-2">
                                    Phone Number <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" id="phone" name="phone" required
                                    value="{{ old('phone') }}"
                                    class="public-input py-2.5 text-sm @error('phone') border-red-500 @enderror"
                                    placeholder="09171234567" />
                                @error('phone')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-slate-700 mb-2">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" required
                                    value="{{ old('email') }}"
                                    class="public-input py-2.5 text-sm @error('email') border-red-500 @enderror"
                                    placeholder="juan.delacruz@email.com" />
                                @error('email')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Complete Address -->
                        <div>
                            <label for="address" class="block text-sm font-medium text-slate-700 mb-2">
                                Complete Address <span class="text-red-500">*</span>
                            </label>
                            <textarea id="address" name="address" rows="3" required
                                class="public-input py-2.5 text-sm"
                                placeholder="House No., Street, Barangay, City/Municipality, Province">{{ old('address') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Employment & Income Details Section -->
                <div class="mb-10">
                    <h2 class="text-xl font-bold text-slate-800 mb-1 flex items-center gap-2">
                        <i class="fas fa-briefcase text-emerald-700"></i>
                        Employment & Income Details
                    </h2>
                    <p class="text-sm text-slate-500 mb-6">Tell us about your current occupation</p>

                    <div class="space-y-5">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label for="occupation" class="block text-sm font-medium text-slate-700 mb-2">
                                    Occupation <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="occupation" name="occupation" required
                                    value="{{ old('occupation') }}"
                                    class="public-input py-2.5 text-sm"
                                    placeholder="e.g., Teacher, Engineer, Business Owner" />
                            </div>
                            <div>
                                <label for="monthly_income" class="block text-sm font-medium text-slate-700 mb-2">
                                    Monthly Income <span class="text-red-500">*</span>
                                </label>
                                <select id="monthly_income" name="monthly_income" required
                                    class="public-input py-2.5 text-sm">
                                    <option value="">Select Income Range</option>
                                    <option value="Below 10000"
                                        {{ old('monthly_income') == 'Below 10000' ? 'selected' : '' }}>Below ₱10,000
                                    </option>
                                    <option value="10000-20000"
                                        {{ old('monthly_income') == '10000-20000' ? 'selected' : '' }}>₱10,000 -
                                        ₱20,000
                                    </option>
                                    <option value="20001-30000"
                                        {{ old('monthly_income') == '20001-30000' ? 'selected' : '' }}>₱20,001 -
                                        ₱30,000
                                    </option>
                                    <option value="30001-50000"
                                        {{ old('monthly_income') == '30001-50000' ? 'selected' : '' }}>₱30,001 -
                                        ₱50,000
                                    </option>
                                    <option value="50001-75000"
                                        {{ old('monthly_income') == '50001-75000' ? 'selected' : '' }}>₱50,001 -
                                        ₱75,000
                                    </option>
                                    <option value="Above 75000"
                                        {{ old('monthly_income') == 'Above 75000' ? 'selected' : '' }}>Above ₱75,000
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Membership Details Section -->
                <div class="mb-10">
                    <h2 class="text-xl font-bold text-slate-800 mb-1 flex items-center gap-2">
                        <i class="fas fa-id-card text-emerald-700"></i>
                        Membership Details
                    </h2>
                    <p class="text-sm text-slate-500 mb-6">Additional membership information</p>

                    <div class="space-y-5">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label for="membership_type" class="block text-sm font-medium text-slate-700 mb-2">
                                    Membership Type <span class="text-red-500">*</span>
                                </label>
                                <select id="membership_type" name="membership_type" required
                                    class="public-input py-2.5 text-sm">
                                    <option value="">Select Type</option>
                                    <option value="Regular"
                                        {{ old('membership_type') == 'Regular' ? 'selected' : '' }}>
                                        Regular Member</option>
                                    <option value="Associate"
                                        {{ old('membership_type') == 'Associate' ? 'selected' : '' }}>
                                        Associate Member</option>
                                </select>
                            </div>
                            <div>
                                <label for="referred_by" class="block text-sm font-medium text-slate-700 mb-2">
                                    Referred By (Optional)
                                </label>
                                <input type="text" id="referred_by" name="referred_by"
                                    value="{{ old('referred_by') }}"
                                    class="public-input py-2.5 text-sm"
                                    placeholder="Name of referring member" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Credentials Section -->
                <div class="mb-10">
                    <h2 class="text-xl font-bold text-slate-800 mb-1 flex items-center gap-2">
                        <i class="fas fa-lock text-emerald-700"></i>
                        Account Credentials
                    </h2>
                    <p class="text-sm text-slate-500 mb-6">Create a secure password for your account</p>

                    <div class="space-y-5">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label for="password" class="block text-sm font-medium text-slate-700 mb-2">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input :type="showPassword ? 'text' : 'password'" id="password" name="password"
                                        required
                                        class="public-input py-2.5 pr-12 text-sm @error('password') border-red-500 @enderror"
                                        placeholder="Minimum 8 characters" />
                                    <button type="button" @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                        <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="password_confirmation"
                                    class="block text-sm font-medium text-slate-700 mb-2">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input :type="showPasswordConfirm ? 'text' : 'password'" id="password_confirmation"
                                        name="password_confirmation" required
                                        class="public-input py-2.5 pr-12 text-sm"
                                        placeholder="Re-enter password" />
                                    <button type="button" @click="showPasswordConfirm = !showPasswordConfirm"
                                        class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                        <i :class="showPasswordConfirm ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Terms & Conditions -->
                <div class="mb-8 p-6 bg-gray-50 border border-gray-200 rounded-lg">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" id="terms" name="terms" required {{ old('terms') ? 'checked' : '' }}
                            class="w-5 h-5 text-emerald-700 border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 cursor-pointer mt-0.5 shrink-0" />
                        <label for="terms" class="text-sm text-slate-700 cursor-pointer">
                            I agree to the
                            <button type="button" @click.prevent="activePolicyModal = 'terms'"
                                class="text-emerald-700 hover:text-emerald-800 font-medium underline underline-offset-2">
                                Terms and Agreement
                            </button>
                            and
                            <button type="button" @click.prevent="activePolicyModal = 'privacy'"
                                class="text-emerald-700 hover:text-emerald-800 font-medium underline underline-offset-2">
                                Privacy Policy
                            </button>
                            of Settlers Credit Cooperative. I understand the rights and responsibilities of cooperative
                            membership. <span class="text-red-500">*</span>
                        </label>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Review both documents before submitting your application.</p>
                    @error('terms')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-8">
                    @if (config('services.turnstile.site_key'))
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"
                            data-theme="light" data-callback="turnstileCallback"></div>
                    @else
                        <p class="text-sm text-red-600">Captcha is not configured. Please contact support.</p>
                    @endif
                    @error('cf-turnstile-response')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit Button -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <button type="submit" id="registerSubmitBtn"
                        class="public-btn public-btn-primary flex-1 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        <i class="fas fa-user-plus mr-2"></i>
                        Submit Application
                    </button>
                    <a href="{{ route('login_page') }}"
                        class="flex-1 sm:flex-initial bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3.5 px-6 rounded-lg transition-all text-center">
                        Already have an account?
                    </a>
                </div>
            </form>
        </div>
    </section>

    @include('partials.policies.modal', ['termsContent' => $termsContent, 'privacyContent' => $privacyContent])

    <script>
        function registrationForm() {
            return {
                birthdate: '',
                age: '',
                showPassword: false,
                showPasswordConfirm: false,
                activePolicyModal: null,
                calculateAge() {
                    if (!this.birthdate) {
                        this.age = '';
                        return;
                    }
                    const today = new Date();
                    const birthDate = new Date(this.birthdate);
                    let age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();

                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                        age--;
                    }

                    this.age = age;
                }
            }
        }

        // Registration form: enable submit button only when Turnstile succeeds
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.getElementById('registerSubmitBtn');

            if (submitBtn) {
                submitBtn.disabled = true;

                window.turnstileCallback = function() {
                    submitBtn.disabled = false;
                };
            }
        });
    </script>

</body>

</html>
