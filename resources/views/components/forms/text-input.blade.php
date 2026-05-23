@props([
    'name',
    'label',
    'model',
    'type' => 'text',
    'required' => false,
    'placeholder' => '',
    'helperText' => '',
    'disabled' => false,
    'readonly' => false,
    'maxlength' => null,
    'validateOnBlur' => true,
    'defer' => false,
    // Numeric / date constraints
    'step' => null,
    'min' => null,
    'max' => null,
    // Mobile keyboard hint (numeric, decimal, tel, email, url, search)
    'inputmode' => null,
    // Browser autocomplete token (e.g. email, current-password, off)
    'autocomplete' => null,
    // Optional leading symbol displayed inside the input (e.g. '₱', '$')
    'prefix' => null,
])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $model);
    $hasError = $errors->has($errorKey);
    $isPassword = $type === 'password';
    $inputPadding = $isPassword ? 'pr-20' : 'pr-10';

    // Get the value from Livewire component
    $keys = explode('.', $model);
    $value = $this;
    foreach ($keys as $key) {
        $value = $value->{$key} ?? null;
    }
    $isEmpty = $required && empty(trim($value ?? ''));
@endphp

<div class="space-y-1">
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>

    <div class="relative" @if ($isPassword) x-data="{ showPassword: false }" @endif>
        @if ($prefix)
            <span
                class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm pointer-events-none select-none">{{ $prefix }}</span>
        @endif
        <input
            @if ($isPassword) x-bind:type="showPassword ? 'text' : 'password'" type="password" @else type="{{ $type }}" @endif
            id="{{ $name }}"
            @if ($defer) wire:model.defer="{{ $model }}" @else wire:model="{{ $model }}" @endif
            @if ($validateOnBlur) wire:blur="validateField('{{ $model }}')" @endif
            placeholder="{{ $placeholder }}" @if ($disabled) disabled @endif
            @if ($readonly) readonly @endif
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            @if ($required) required @endif
            @if ($step !== null) step="{{ $step }}" @endif
            @if ($min !== null) min="{{ $min }}" @endif
            @if ($max !== null) max="{{ $max }}" @endif
            @if ($inputmode) inputmode="{{ $inputmode }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            {{ $attributes->except('class') }}
            class="peer block w-full {{ $prefix ? 'pl-7' : 'px-3' }} py-2 {{ $inputPadding }} text-sm border rounded-lg transition-all duration-200 focus:outline-none focus:ring-2
                @if ($hasError) border-red-500 text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500
                @else
                    border-gray-300 text-gray-900 focus:ring-cyan-500 focus:border-cyan-500
                    [&:user-invalid]:border-red-500 [&:user-invalid]:focus:ring-red-500 [&:user-invalid]:focus:border-red-500
                    [&:required:user-valid]:border-green-500 [&:required:user-valid]:focus:ring-green-500 [&:required:user-valid]:focus:border-green-500 @endif
                @if ($disabled) bg-gray-100 cursor-not-allowed @else bg-white @endif">

        {{-- Error Icon: shown by server error OR instantly by client :user-invalid --}}
        <div
            class="absolute inset-y-0 {{ $isPassword ? 'right-10' : 'right-0' }} flex items-center pr-3 pointer-events-none
                {{ $hasError ? '' : 'hidden peer-[:user-invalid]:flex' }}">
            <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
        </div>

        @if ($isPassword)
            <button type="button" x-on:click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 transition-colors hover:text-gray-700"
                aria-label="Toggle password visibility">
                <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 12s3.5-6.75 9.75-6.75S21.75 12 21.75 12 18.25 18.75 12 18.75 2.25 12 2.25 12z" />
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 15.75a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z" />
                </svg>
                <svg x-show="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3.98 8.223A10.477 10.477 0 002.25 12s3.5 6.75 9.75 6.75c1.5 0 2.86-.27 4.07-.74" />
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M6.53 6.53A10.453 10.453 0 0112 5.25C18.25 5.25 21.75 12 21.75 12a18.64 18.64 0 01-3.34 4.63M9.88 9.88a3.75 3.75 0 005.3 5.3" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                </svg>
            </button>
        @endif
    </div>

    {{-- Helper Text or Error Message --}}
    @if ($hasError)
        <p class="text-sm text-red-600 flex items-center gap-1">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
            {{ $errors->first($errorKey) }}
        </p>
    @elseif($helperText)
        <p class="text-sm text-gray-500 flex items-center gap-1">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clip-rule="evenodd" />
            </svg>
            {{ $helperText }}
        </p>
    @endif
</div>
