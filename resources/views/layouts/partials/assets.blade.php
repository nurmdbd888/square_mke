@php($asset = fn (string $path) => asset($path).(is_file(public_path($path)) ? '?v='.filemtime(public_path($path)) : ''))
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#0f766e">
<link rel="stylesheet" href="{{ $asset('css/app.css') }}">
<script src="{{ $asset('js/htmx.min.js') }}" defer></script>
{{-- app.js / ocr-scan.js register Alpine factories before Alpine.start() --}}
<script src="{{ $asset('js/app.js') }}" defer></script>
<script src="{{ $asset('js/ocr-scan.js') }}" defer></script>
<script src="{{ $asset('js/alpine.min.js') }}" defer></script>
@auth
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function () {});
        });
    }
</script>
@endauth
@stack('scripts')
