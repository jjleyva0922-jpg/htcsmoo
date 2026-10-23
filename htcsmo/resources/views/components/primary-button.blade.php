<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-dark component-button']) }}>
    {{ $slot }}
</button>
