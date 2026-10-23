@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'field-errors small text-danger']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
