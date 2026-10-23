@props(['active'])

@php
$classes = ($active ?? false)
            ? 'component-nav-link is-active'
            : 'component-nav-link';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
