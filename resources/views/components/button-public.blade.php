@props([
    'label' => 'Button',
    'icon' => null,
    'href' => '#',
    'extraClass' => null,
])



<a href="{{ $href }}"
    class="inline-flex items-center text-center px-8 py-4 {{ $extraClass ?? '' }} bg-linear-to-r from-emerald-500 to-cyan-500 hover:from-primary1 hover:to-primary2 text-white font-semibold rounded-full shadow-lg hover:shadow-xl transition-all transform hover:scale-105">

    {{ $label }}

    @if ($icon)
        <i class="{{ $icon }}"></i>
    @endif

</a>
