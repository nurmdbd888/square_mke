@props([
    'href',
])

<a href="{{ $href }}" {{ $attributes->class(['btn-secondary']) }} data-testid="back-link">{{ $slot }}</a>
