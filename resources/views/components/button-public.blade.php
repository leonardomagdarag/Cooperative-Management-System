@props([
    'label' => 'Button',
    'icon' => null,
    'href' => '#',
    'extraClass' => null,
])



<a href="{{ $href }}"
    class="public-btn public-btn-primary text-center {{ $extraClass ?? '' }}">

    {{ $label }}

    @if ($icon)
        <i class="{{ $icon }}"></i>
    @endif

</a>
