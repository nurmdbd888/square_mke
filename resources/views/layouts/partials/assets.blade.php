@php($asset = fn (string $path) => asset($path).(is_file(public_path($path)) ? '?v='.filemtime(public_path($path)) : ''))
<link rel="stylesheet" href="{{ $asset('css/app.css') }}">
<script src="{{ $asset('js/htmx.min.js') }}" defer></script>
<script src="{{ $asset('js/alpine.min.js') }}" defer></script>
<script src="{{ $asset('js/app.js') }}" defer></script>
