<!DOCTYPE html>
<html lang="es" class="h-full bg-zinc-950 text-zinc-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Presupuesto Aprobado') }} · {{ $tenant?->name ?? 'NUME' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.favicons')
</head>
<body class="min-h-full flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full text-center space-y-6 bg-zinc-900/80 border border-zinc-800 rounded-3xl p-8 backdrop-blur-sm">
        <div class="w-16 h-16 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl flex items-center justify-center mx-auto text-emerald-400">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-400">
                {{ __('Propuesta Aceptada') }}
            </span>
            <h1 class="text-2xl font-extrabold text-white mt-1">
                {{ __('¡Presupuesto Aprobado con Éxito!') }}
            </h1>
            <p class="text-xs text-zinc-400 font-mono mt-1">
                #{{ $quote->number }} · {{ $quote->project_title ?: __('Producción Audiovisual') }}
            </p>
        </div>

        <p class="text-sm text-zinc-300 leading-relaxed">
            {{ __('El pago de la señal ha sido registrado y el proyecto ya se encuentra en marcha. Nuestro equipo de producción está organizando los recursos y la pre-producción.') }}
        </p>

        @if($quote->accepted_at)
            <div class="p-3 bg-zinc-950/60 rounded-xl border border-zinc-800 text-xs text-zinc-400">
                {{ __('Confirmado el') }} {{ $quote->accepted_at->format('d/m/Y H:i') }}
            </div>
        @endif

        <div class="pt-4 border-t border-zinc-800 text-xs text-zinc-400">
            {{ $tenant?->name ?? 'NUME Creative Studio' }}
        </div>
    </div>
</body>
</html>
