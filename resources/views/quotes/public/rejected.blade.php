<!DOCTYPE html>
<html lang="es" class="h-full bg-zinc-950 text-zinc-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Propuesta Rechazada') }} · {{ $tenant?->name ?? 'NUME' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.favicons')
</head>
<body class="min-h-full flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full text-center space-y-6 bg-zinc-900/80 border border-zinc-800 rounded-3xl p-8 backdrop-blur-sm">
        <div class="w-16 h-16 bg-zinc-800 border border-zinc-700 rounded-2xl flex items-center justify-center mx-auto text-zinc-400">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </div>

        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                {{ __('Propuesta Declinada') }}
            </span>
            <h1 class="text-2xl font-extrabold text-white mt-1">
                {{ __('Presupuesto Declinado') }}
            </h1>
            <p class="text-xs text-zinc-400 font-mono mt-1">
                #{{ $quote->number }}
            </p>
        </div>

        <p class="text-sm text-zinc-400 leading-relaxed">
            {{ __('Has indicado que esta propuesta no se ajusta a tus necesidades actuales. Si deseas un reajuste de alcance, formato o plazos de entrega, ponte en contacto con nosotros.') }}
        </p>

        <div class="pt-4 border-t border-zinc-800 text-xs text-zinc-400">
            {{ $tenant?->name ?? 'NUME Creative Studio' }}
        </div>
    </div>
</body>
</html>
