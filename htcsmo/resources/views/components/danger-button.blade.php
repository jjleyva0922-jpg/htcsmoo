<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-danger component-button']) }}>
    {{ $slot }}
</button>
