@props([
    'name',
    'label',
    'model',
    'required' => false,
    'placeholder' => '',
    'helperText' => '',
    'disabled' => false,
    'rows' => 3,
    'maxlength' => null,
])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $model);
    $hasError = $errors->has($errorKey);
@endphp

<div class="space-y-1">
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>

    <div class="relative">
        <textarea id="{{ $name }}" wire:model="{{ $model }}" placeholder="{{ $placeholder }}"
            rows="{{ $rows }}" @if ($disabled) disabled @endif
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            @if ($required) required @endif
            class="block w-full px-3 py-2 text-sm border rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 resize-none
                @if ($hasError) border-red-500 text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500
                @else
                    border-gray-300 text-gray-900 focus:ring-cyan-500 focus:border-cyan-500 @endif
                @if ($disabled) bg-gray-100 cursor-not-allowed @else bg-white @endif"></textarea>
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
