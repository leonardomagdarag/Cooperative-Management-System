{{-- navigation link button --}}
@props(['section', 'icon', 'label', 'active' => false])

<button wire:click="updateSection('{{ $section }}')" wire:loading.attr="disabled"
    wire:target="updateSection" @class([
        'w-full flex items-center justify-center lg:justify-start gap-3 px-4 py-3 rounded-lg transition-all duration-200',
        'bg-gradient-to-r from-emerald-500 to-cyan-500 text-white shadow-md' => $active,
        'text-gray-700 hover:bg-emerald-50 hover:text-emerald-600' => !$active,
        'disabled:opacity-50 disabled:cursor-not-allowed',
    ])>
    <i class="{{ $icon }} text-lg shrink-0"></i>

    <span
        class="hidden lg:flex group-hover:flex flex-1 items-center justify-between text-left font-medium whitespace-nowrap overflow-hidden">
        <span class="truncate">{{ $label }}</span>
        <i class="fas fa-spinner fa-spin text-sm shrink-0" wire:loading
            wire:target="updateSection('{{ $section }}')"></i>
    </span>
</button>
