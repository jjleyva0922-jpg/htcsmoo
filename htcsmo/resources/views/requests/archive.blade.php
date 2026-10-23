<x-app-layout>
    <x-slot name="header">{{ $institutional ? __('Institutional Archive') : __('My Request Archive') }}</x-slot>
    @include('requests.partials.archive-workspace')
</x-app-layout>
