@props(['active'])

@php
$classes = ($active ?? false)
            ? 'component-responsive-nav-link is-active'
            : 'component-responsive-nav-link';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
