@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidth = [
    'sm' => 'component-modal-sm',
    'md' => 'component-modal-md',
    'lg' => 'component-modal-lg',
    'xl' => 'component-modal-xl',
    '2xl' => 'component-modal-2xl',
][$maxWidth];
@endphp

<div
    x-data="{
        show: @js($show),
        focusables() {
            // All focusable element types...
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                // All non-disabled elements...
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-init="$watch('show', value => {
        if (value) {
            document.body.classList.add('component-modal-open');
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            document.body.classList.remove('component-modal-open');
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    class="component-modal"
    role="dialog"
    aria-modal="true"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div
        x-show="show"
        class="component-modal-backdrop"
        x-on:click="show = false"
        x-transition:enter="component-modal-enter"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="component-modal-visible"
        x-transition:leave="component-modal-leave"
        x-transition:leave-start="component-modal-visible"
        x-transition:leave-end="opacity-0"
    >
    </div>

    <div
        x-show="show"
        class="component-modal-panel bg-white rounded-3 overflow-hidden shadow-lg {{ $maxWidth }}"
        x-transition:enter="component-modal-enter"
        x-transition:enter-start="component-modal-hidden"
        x-transition:enter-end="component-modal-visible"
        x-transition:leave="component-modal-leave"
        x-transition:leave-start="component-modal-visible"
        x-transition:leave-end="component-modal-hidden"
    >
        {{ $slot }}
    </div>
</div>
