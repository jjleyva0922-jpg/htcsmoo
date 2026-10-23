@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white'])

@php
$alignmentClasses = match ($align) {
    'left' => 'component-dropdown-start',
    'top' => 'component-dropdown-top',
    default => 'component-dropdown-end',
};

$width = match ($width) {
    '48' => 'component-dropdown-standard',
    default => $width,
};
@endphp

<div class="position-relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="component-dropdown-enter"
            x-transition:enter-start="component-dropdown-hidden"
            x-transition:enter-end="component-dropdown-visible"
            x-transition:leave="component-dropdown-leave"
            x-transition:leave-start="component-dropdown-visible"
            x-transition:leave-end="component-dropdown-hidden"
            class="component-dropdown mt-2 rounded shadow-lg {{ $width }} {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded border {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
