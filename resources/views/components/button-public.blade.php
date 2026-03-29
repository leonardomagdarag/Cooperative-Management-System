@props([
    'label' => 'Button',
    'icon' => null,
    'href' => '#',
    'extraClass' => null,
])



<a href="{{ $href }}"
    class="inline-flex items-center text-center px-6 py-3 {{ $extraClass ?? '' }} bg-emerald-700 hover:bg-linear-to-r hover:from-emerald-700 hover:to-emerald-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:scale-102">

    {{ $label }}

    @if ($icon)
        <i class="{{ $icon }}"></i>
    @endif

</a>
