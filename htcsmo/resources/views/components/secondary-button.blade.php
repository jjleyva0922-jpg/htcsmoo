<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-outline-secondary component-button shadow-sm']) }}>
    {{ $slot }}
</button>
